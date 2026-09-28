# AegisDock Attack Path

## Phase 1: Recon

### Objective: Identify application components

**Commands:**
```bash
# Discover services
curl http://localhost/
curl http://localhost/api/
curl http://localhost:5000/
curl http://localhost:9000/

# Check health endpoints
curl http://localhost/api/me
curl http://localhost:5000/images
curl http://localhost:9000/logs
```

**Expected Findings:**
- Frontend: Next.js dashboard on port 3000
- API: Laravel backend on port 8000
- Registry: Simulator on port 5000
- Audit: Service on port 9000
- Nginx: Reverse proxy on port 80

## Phase 2: Application Mapping

### Objective: Understand authentication and authorization

**Commands:**
```bash
# Test login
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "operator@aegisdock.local", "password": "Operator123!"}'

# Explore API
curl http://localhost/api/me -H "Authorization: Bearer <token>"
curl http://localhost/api/terminals
curl http://localhost/api/containers
curl http://localhost/api/containers/CONT-88421
```

**Expected Findings:**
- Authentication via Sanctum tokens
- Role-based access: operator, supervisor, security_admin
- Container operations: VIEW, INSPECT, HOLD, RELEASE
- Authorization artifacts used for sensitive operations

## Phase 3: Container Investigation

### Objective: Discover container image and metadata

**Commands:**
```bash
# List images in registry simulator
curl http://localhost:5000/images

# Get specific image metadata
curl http://localhost:5000/images/aegisdock-terminal-api

# Get build history
curl http://localhost:5000/artifact/aegisdock-terminal-api/history

# Get layer information
curl http://localhost:5000/debug/layers/aegisdock-terminal-api
```

**Expected Findings:**
- Image: aegisdock-terminal-api:v1.2.0-training
- Build history shows 7 layers
- Layer 3 contains suspicious COPY command
- Secret flag in layer 3: "COPY deploy/archive/terminal-gateway.env.bak"

## Phase 4: Secret Discovery

### Objective: Recover leaked cryptographic key

**Commands:**
```bash
# Pull the vulnerable image
docker pull aegisdock/api:vulnerable

# Extract filesystem layers
docker create --name extract aegisdock/api:vulnerable
docker export extract | tar -x

# Search for secrets
find . -name "*.bak" -o -name "*.env*" -o -name "*.key" -o -name "*.pem" | head -10
grep -r "TRAINING-FAKE-KEY" . 2>/dev/null || true
grep -r "TERMINAL_CRYPTO_KEY" . 2>/dev/null || true

# Alternative: Use docker history
docker history aegisdock/api:vulnerable --no-trunc
```

**Expected Findings:**
- File: deploy/archive/terminal-gateway.env.bak
- Contents:
  ```
  TERMINAL_CRYPTO_KEY=TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
  TERMINAL_CRYPTO_IV=TRAINING-FAKE-IV-1234567890ABC
  TERMINAL_API_URL=http://terminal-controller:9000
  ```

## Phase 5: Crypto Analysis

### Objective: Identify cryptographic construction

**Commands:**
```bash
# Get authorization artifact
curl -X GET "http://localhost/api/authorization/artifact?terminal=T-07&container=CONT-88421" \
  -H "Authorization: Bearer <operator-token>"

# Get debug information for artifact
curl -X GET "http://localhost/api/debug/authorization/1" \
  -H "Authorization: Bearer <operator-token>"

# Analyze artifact structure
echo "<base64-artifact>" | base64 -d | xxd
```

**Expected Findings:**
- Algorithm: AES-256-CBC
- IV: TRAINING-FAKE-IV-1234567890ABC (static)
- Key: TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
- Plaintext structure: {"operator_id":1001,"terminal":"T-07","container":"CONT-88421","action":"VIEW",...}
- No HMAC or authentication tag

## Phase 6: Authorization Artifact Discovery

### Objective: Obtain legitimate authorization artifact

**Commands:**
```bash
# Login as operator
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "operator@aegisdock.local", "password": "Operator123!"}'

# Get artifact for T-07/CONT-88421
curl -X GET "http://localhost/api/authorization/artifact?terminal=T-07&container=CONT-88421" \
  -H "Authorization: Bearer <operator-token>"
```

**Expected Findings:**
- Artifact: Base64-encoded ciphertext
- Decrypts to: {"operator_id":1001,"terminal":"T-07","container":"CONT-88421","action":"VIEW",...}
- Action field shows VIEW (insufficient for release)

## Phase 7: Controlled Exploitation

### Objective: Demonstrate ciphertext manipulation

**Commands:**
```bash
# Decode artifact to see structure
echo "<base64-artifact>" | base64 -d > ciphertext.bin
xxd ciphertext.bin

# Calculate block boundaries for CBC manipulation
# AES block size = 16 bytes
# Plaintext: {"operator_id":1001,"terminal":"T-07","container":"CONT-88421","action":"VIEW",...

# Example: Change action from VIEW to RELEASE by flipping specific bits
# In CBC mode, flipping bit N in ciphertext block i flips same bit in plaintext block i+1
# Need to locate where "VIEW" appears in plaintext and flip to "RELEASE"

# Actual manipulation demonstrated in training environment
```

**Expected Findings:**
- CBC mode allows bit flipping in previous block to change next plaintext block
- Static IV means same IV every time = predictable structure
- No integrity check = tampered ciphertext decrypts to modified plaintext
- Server trusts decrypted action field without verification

## Phase 8: Impact

### Objective: Achieve unauthorized container release

**Commands:**
```bash
# Create manipulated artifact
# (In training: use Python/OpenSSL to flip bits to change VIEW→RELEASE)

# Attempt release with manipulated artifact
curl -X POST http://localhost/api/containers/CONT-88421/release \
  -H "Authorization: Bearer <operator-token>" \
  -H "X-Terminal-Authorization: <manipulated-artifact>" \
  -H "Content-Type: application/json" \
  -d '{"reason": "authorized release"}'

# Check result - should succeed despite operator role
```

**Expected Findings:**
- Operator (normally cannot release) successfully releases container
- Container status changes from READY to RELEASED
- Audit log shows AUTHORIZATION_SUCCESS and CONTAINER_RELEASE

## Phase 9: Forensics

### Objective: Investigate audit trail

**Commands:**
```bash
# Check audit logs
curl http://localhost/api/audit -H "Authorization: Bearer <token>"

# Check audit service directly
curl http://localhost:9000/logs

# Look for cryptographic events
curl http://localhost:9000/logs?event=AUTHORIZATION_SUCCESS
curl http://localhost:9000/logs?event=CONTAINER_RELEASE
```

**Expected Findings:**
- AUTHORIZATION_SUCCESS event with manipulated artifact
- CONTAINER_RELEASE event showing unauthorized operation
- No encryption keys leaked in logs (but IV and algorithm visible in debug endpoint)
- Timeline shows: login → artifact request → release attempt → release success

## Phase 10: Remediation

### Objective: Apply fixes and verify

**Commands:**
```bash
# Build secure image
make build-secure

# Update docker-compose to use secure image
# (Change api service to use docker/secure/Dockerfile)

# Restart services
make reset

# Test that exploit no longer works
# Same steps as Phase 8 should now fail

# Verify secure properties:
# - AES-GCM with unique nonce
# - Authentication tag verified
# - Server-side role enforcement primary
# - No secrets in image layers
```

**Expected Findings:**
- Docker history shows no secret leakage
- Secure image uses runtime secret injection
- AES-GCM rejects tampered ciphertext
- Operator cannot release containers even with manipulated artifact
- Audit logs show AUTHORIZATION_FAILURE for tampered artifacts