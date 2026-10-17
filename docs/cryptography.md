# AegisDock Cryptography

## Vulnerable mode: AES-256-CBC

The vulnerable implementation deliberately combines three design errors:

1. A static IV is reused for artifact encryption.
2. The transport format contains the IV and ciphertext but no MAC or authentication tag.
3. The release endpoint trusts the decrypted action claim instead of enforcing the role policy first.

AES itself is not broken. The failure is using a confidentiality-only construction as if it also authenticated the data.

### Artifact format

The vulnerable artifact is Base64-encoded binary data containing an IV followed by CBC ciphertext. The API exposes algorithm and length metadata through the authenticated debug endpoint, but it does not return the encryption key.

The training plaintext uses a fixed-width action header followed by metadata. This makes the malleability exercise deterministic rather than a blind guessing challenge. Learners should inspect the artifact and the application implementation as part of the lab.

### CBC malleability

CBC decryption follows:

```text
P1 = D_K(C1) XOR IV
Pi = D_K(Ci) XOR C(i-1), for i > 1
```

Because the IV is not authenticated, changing the IV changes the first plaintext block predictably. CBC ciphertext alone does not provide integrity, and the application does not verify a MAC or authentication tag before trusting the result.

This does not mean an attacker can arbitrarily change every byte of any encrypted message. The practical effect depends on block layout, encoding, parsing, and application validation.

### Impact in AegisDock

A normal operator receives an artifact for a VIEW action. The vulnerable release path accepts a decrypted RELEASE action without independently checking the server-side role when an artifact is supplied. The resulting operation changes only the simulated container state in the local database.

## Secure mode: AES-256-GCM

The secure implementation uses authenticated encryption with a unique nonce per artifact.

- A 256-bit key is read from a runtime-only secret file.
- A 96-bit nonce is generated for each encryption.
- The authentication tag is verified before the plaintext is returned.
- Modified artifacts are rejected.
- The release endpoint checks server-side role authorization before considering an artifact.
- Supplied artifacts are bound to the authenticated user, terminal, container, action, and expiry.

The secure implementation uses the AEAD API exposed by libsodium/PHP. The returned artifact contains the nonce and authenticated ciphertext; it does not expose a reusable static IV.

## Key management

The vulnerable key/IV are generated locally by `scripts/init-local-secrets.sh`. The vulnerable Docker build copies a temporary fake configuration into a historical layer to model an accidental deployment leak. The secure image excludes both the archive and the local secrets directory.

Never put production secrets in source code, Docker build arguments, image layers, logs, or a checked-in environment file. If a real secret is leaked, revoke/rotate it and rebuild every affected artifact.
