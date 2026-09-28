# AegisDock Security Scanning Guide

## Docker Image Analysis

### 1. Docker History Inspection

```bash
# View full layer history
docker history aegisdock/api:vulnerable --no-trunc

# Expected vulnerable output:
# IMAGE          CREATED          CREATED BY
# abc123...      2025-01-15       RUN rm /opt/legacy/terminal-gateway.env.bak
# def456...      2025-01-15       COPY api/ .
# ghi789...      2025-01-15       COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak
# jkl012...      2025-01-15       FROM php:8.3-apache
```

### 2. Docker Save & Extract

```bash
# Save image to tar
docker save aegisdock/api:vulnerable -o vulnerable-image.tar

# Extract layers
tar -xf vulnerable-image.tar

# Each layer is a directory with layer.tar
# Search for secrets in layer files
grep -r "TERMINAL_CRYPTO_KEY" . 2>/dev/null || true
grep -r "TRAINING-FAKE" . 2>/dev/null || true
```

### 3. Docker Inspect

```bash
# Get image configuration
docker image inspect aegisdock/api:vulnerable

# Look for environment variables that may leak secrets
```

## Trivy Scanning

```bash
# Scan vulnerable image
trivy image aegisdock/api:vulnerable

# Scan for secrets
trivy image --scanners secret aegisdock/api:vulnerable

# Scan secure image for comparison
trivy image aegisdock/api:secure
```

## Manual Secret Detection

```bash
# Strings analysis
docker save aegisdock/api:vulnerable | tar -xO | strings | grep -i "training\|fake\|secret\|key"

# File system analysis
docker create --name extract aegisdock/api:vulnerable
docker export extract | tar -x
find . -type f \( -name "*.env*" -o -name "*.bak" -o -name "*.key" -o -name "*.pem" \) 2>/dev/null
```

## API Security Testing

### Burp Suite Configuration

1. **Import collection**: Use the provided Postman collection
2. **Configure proxy**: Set browser proxy to Burp
3. **Intercept**: Enable interception for all API calls
4. **Key endpoints to monitor**:
   - `POST /api/login`
   - `GET /api/authorization/artifact`
   - `POST /api/containers/*/release`
   - `GET /api/debug/authorization/*`

### curl Test Commands

```bash
# 1. Get operator token
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "operator@aegisdock.local", "password": "Operator123!"}'

# 2. Get authorization artifact
curl -X GET "http://localhost/api/authorization/artifact?terminal=T-07&container=CONT-88421" \
  -H "Authorization: Bearer <token>"

# 3. Attempt release with artifact
curl -X POST http://localhost/api/containers/CONT-88421/release \
  -H "Authorization: Bearer <token>" \
  -H "X-Terminal-Authorization: <artifact>" \
  -H "Content-Type: application/json" \
  -d '{"reason": "test"}'
```

## OpenSSL Analysis

```bash
# Decode and analyze artifact
echo "<base64-artifact>" | base64 -d > artifact.bin

# View hex dump
xxd artifact.bin

# Check block structure (16 bytes per block for AES)
# For AES-256-CBC: block size = 128 bits = 16 bytes
openssl enc -d -aes-256-cbc -K <key-hex> -iv <iv-hex> -in artifact.bin -out decrypted.txt

# View decrypted
cat decrypted.txt
```

## Verification Checklist

### Vulnerable Image (Expected Findings)

- [ ] `docker history` shows COPY deploy/archive command
- [ ] `docker history` shows RUN rm command later
- [ ] Secret found in extracted layers
- [ ] Trivy reports secret in image
- [ ] Trivy reports no secrets in secure image
- [ ] Artifact decrypts with recovered key/IV
- [ ] Static IV confirmed (same ciphertext for same plaintext)
- [ ] No HMAC/tag in ciphertext
- [ ] Operator + manipulated artifact = release success

### Secure Image (Expected Findings)

- [ ] No deploy/archive references in history
- [ ] No TRAINING-FAKE secrets in any layer
- [ ] .dockerignore excludes deploy/archive
- [ ] Secrets injected via environment at runtime
- [ ] AES-GCM used (unique nonce per encryption)
- [ ] Authentication tag verified
- [ ] Tampered ciphertext rejected
- [ ] Operator cannot release even with artifact
- [ ] Server-side role check is primary

## Automated Verification Script

```bash
#!/bin/bash
# verify.sh

echo "=== AegisDock Security Verification ==="
echo ""

# Test 1: Vulnerable image has secret
echo "Test 1: Vulnerable image contains training key"
if docker history aegisdock/api:vulnerable --no-trunc | grep -q "terminal-gateway.env.bak"; then
    echo "  PASS: Secret layer found in history"
else
    echo "  FAIL: Secret layer not visible"
fi

# Test 2: Secure image clean
echo "Test 2: Secure image has no training key"
if ! docker history aegisdock/api:secure --no-trunc | grep -q "TRAINING-FAKE"; then
    echo "  PASS: No secrets in secure image"
else
    echo "  FAIL: Secrets found in secure image"
fi

# Test 3: Exploit works on vulnerable
echo "Test 3: Vulnerable API allows unauthorized release"
# (Run exploit script against vulnerable API)

# Test 4: Exploit blocked on secure
echo "Test 4: Secure API blocks unauthorized release"
# (Run exploit script against secure API - should fail)

echo ""
echo "=== Verification Complete ==="
```