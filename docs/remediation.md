# AegisDock Remediation Guide

## Findings and intended fixes

| ID | Finding | Secure control |
|---|---|---|
| DOCKER-01 | Fake key/IV retained in a historical image layer | Exclude secrets from the build context; secure image never copies the deployment archive |
| CRYPTO-01 | AES-CBC used without authentication | Use authenticated encryption (AES-256-GCM) |
| CRYPTO-02 | Static IV reused | Use a fresh nonce for each AEAD encryption |
| CRYPTO-03 | Artifact has no integrity tag | Verify the AEAD authentication tag before trusting plaintext |
| AUTH-01 | Artifact can override the server-side role policy | Enforce role/resource authorization before processing any artifact |
| AUTH-02 | Vulnerable mode does not bind artifact claims to the request context | Validate user, terminal, container, action, and expiry in secure mode |
| AUDIT-01 | Sensitive actions require reliable investigation evidence | Record actor, timestamp, resource, action, outcome, and request context without logging keys |

## 1. Build and key management

The vulnerable profile generates fake key material locally. The secret is mounted into the API container at runtime and a temporary copy is intentionally copied into the vulnerable image layer for the exercise.

The secure profile uses a different runtime-mounted key. Its Dockerfile does not copy the archive or the local secrets directory.

```bash
make secure-up
docker history --no-trunc aegisdock/api:secure
trivy image aegisdock/api:secure
```

If a real key ever enters an image, revoke/rotate it, remove it from the build context, rebuild the image, and scan the replacement. Deleting the file in a later layer is not remediation.

## 2. Authenticated encryption

Use AES-256-GCM or another correctly implemented AEAD construction. Generate a unique nonce for each encryption, authenticate all security-relevant context where appropriate, and reject artifacts with invalid tags.

Do not invent a custom encryption protocol for production. Prefer the platform's maintained cryptographic library.

## 3. Server-side authorization

The server must derive permission from the authenticated identity, server-side role, resource policy, and current resource state. An encrypted client artifact may be useful as a transport token, but it must not grant authority simply because the server can decrypt it.

The secure release path:
1. authenticates the request,
2. checks the server-side role and resource policy,
3. validates a supplied artifact's AEAD tag and expiry,
4. binds its user/terminal/container/action to the current request,
5. updates only the local simulated container state,
6. records the result in the audit trail.

## 4. Runtime secrets

Do not put secrets in source code, Dockerfile `ARG`, Dockerfile `ENV`, build logs, or image layers. Use Docker secrets, a secret manager, or a protected runtime file mount. Keep generated files under the ignored `secrets/` directory.

## 5. Debugging and audit

The debug endpoint is part of the training scenario and should not be exposed in a production application. It may return algorithm and artifact metadata in the lab, but it must never return key material. Production deployments should restrict or remove such endpoints.

## 6. Verification checklist

- [ ] Operator cannot release a container in secure mode, even with a valid artifact.
- [ ] Supervisor can perform an authorized simulated release.
- [ ] Tampered AEAD artifacts are rejected.
- [ ] Expired secure artifacts are rejected.
- [ ] Secure image layers contain none of the generated key/IV values.
- [ ] Vulnerable image's final filesystem does not expose the removed archive.
- [ ] Runtime secret files are ignored by Git.
- [ ] Audit logs capture release attempts and results without recording keys.
- [ ] Automated API and crypto tests pass.
