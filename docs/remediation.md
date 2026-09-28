# AegisDock Remediation Guide

## Vulnerability Inventory

| ID | Vulnerability | Severity | Fix |
|----|--------------|----------|-----|
| CRYPTO-01 | AES-256-CBC without authentication | High | Use AES-256-GCM |
| CRYPTO-02 | Static IV | High | Unique nonce per encryption |
| CRYPTO-03 | No integrity verification | High | Verify authentication tag |
| CRYPTO-04 | Trust decrypted fields | High | Verify all fields |
| CRYPTO-05 | Artifact overrides server auth | Critical | Primary server-side role check |
| CRYPTO-06 | Bit flipping via CBC | High | AEAD encryption |
| CRYPTO-07 | Authorization bypass | Critical | Server-side enforcement |
| CRYPTO-08 | No replay protection | Medium | Nonce + timestamp validation |
| DOCKER-01 | Secret in image layer | High | Runtime injection |
| DOCKER-02 | .env.bak in Docker history | High | Build-time cleanup |
| AUTH-01 | No rate limiting | Medium | Rate limiting middleware |

## Implementation Steps

### 1. Switch to AES-GCM

Replace `CryptoService.php` with `SecureCryptoService.php`:

```php
// SECURE: Uses AES-256-GCM with unique nonce
$nonce = random_bytes(12);
$ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
    $plaintext,
    '',
    $nonce,
    $key
);
```

### 2. Server-Side Role Enforcement

Make role check primary, artifact secondary:

```php
// SECURE: Server-side check FIRST
if (!in_array($role, ['supervisor', 'security_admin'])) {
    return response()->json([...], 403);
}

// Artifact is for audit trail only
if ($authArtifact) {
    // Validate but don't trust for authorization
    $artifactData = $this->crypto->decryptArtifact($authArtifact);
    Log::info('Artifact validated', $artifactData);
}
```

### 3. Remove Secrets from Docker

Use `.dockerignore` and runtime injection:

```dockerfile
# Dockerfile.secure - No secrets copied
COPY api/ ./  # Application only

# Secrets injected at runtime
# CRYPTO_KEY and CRYPTO_IV come from environment
```

### 4. Add Rate Limiting

```php
// In routes/api.php
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

### 5. Secure Debug Endpoint

Remove or restrict debug endpoint:

```php
// SECURE: Remove debug endpoint or restrict to admin only
// Debug endpoint exposes cryptographic metadata - never in production
```

## Verification

```bash
# Test secure implementation
make test

# Verify no secrets in image
docker history aegisdock/api:secure --no-trunc

# Verify exploit no longer works
# Follow attack path - should now fail at Phase 8
```

## Summary

The remediation addresses the core vulnerability: **the authorization artifact should never override server-side role enforcement**. The fix ensures:

1. **Primary authorization** is server-side role check
2. **Artifacts** are for audit trail only
3. **AEAD encryption** prevents tampering
4. **Unique nonces** prevent pattern analysis
5. **Secrets** never enter Docker images