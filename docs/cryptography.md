# AegisDock Cryptography

## Vulnerable Implementation (AES-256-CBC)

### Weaknesses

1. **Static IV**: Same IV used for every encryption
   - Enables ciphertext manipulation attacks
   - Predictable structure across artifacts

2. **No Integrity Protection**: No HMAC or authentication tag
   - Tampered ciphertext decrypts to modified plaintext
   - Server trusts decrypted fields without verification

3. **AES-CBC Mode**: Cipher Block Chaining
   - Blocks are chained together
   - Bit flipping in block N affects plaintext in block N+1
   - Can manipulate specific plaintext fields

### Technical Details

```php
$cipher = 'aes-256-cbc';  // Vulnerable mode
$key = 'TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12';  // Static key
$iv = 'TRAINING-FAKE-IV-1234567890ABC';  // Static IV

// Encryption
$ciphertext = openssl_encrypt(
    $plaintext,
    $cipher,
    $key,
    OPENSSL_RAW_DATA,
    $iv  // Same IV every time!
);

// No HMAC, no authentication tag
// Just base64 encode and send
```

### Attack Surface

```
Plaintext: {"operator_id":1001,"terminal":"T-07","container":"CONT-88421","action":"VIEW",...
                                                          ^^^^^
                                                          Target field

AES-256-CBC Block Structure:
Block 1: {"operator_id":1001
Block 2: "terminal":"T-07",
Block 3: "container":"CONT-88421",
Block 4: "action":"VIEW",       ← Target: flip bits to change VIEW → RELEASE
Block 5: "issued_at":1770000000
Block 6: "expires_at":1770003600
```

## Secure Implementation (AES-256-GCM)

### Improvements

1. **Unique Nonce**: Random nonce for every encryption
   - Prevents ciphertext pattern analysis
   - Each artifact encrypted independently

2. **Authentication Tag**: GCM mode provides built-in authentication
   - Tampered ciphertext fails decryption
   - Server rejects tampered artifacts

3. **Integrity Verification**: Authentication tag verified before trusting plaintext
   - Decryption fails if ciphertext modified
   - No bit flipping attacks possible

### Technical Details

```php
$cipher = 'aes-256-gcm';  // Secure mode
$key = base64_decode(config('crypto.secure_key'));  // Runtime injection
$nonce = random_bytes(12);  // Unique nonce per encryption

// Encryption with authentication
$ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
    $plaintext,
    '',  // additional data
    $nonce,
    $key
);

// Format: nonce + ciphertext (includes authentication tag)
return base64_encode($nonce . $ciphertext);
```

### Security Properties

```
┌─────────────────────┐     ┌─────────────────────┐
│   Vulnerable        │     │   Secure            │
├─────────────────────┤     ├─────────────────────┤
│ AES-256-CBC         │     │ AES-256-GCM         │
│ Static IV           │     │ Unique nonce        │
│ No HMAC             │     │ Auth tag (128-bit)  │
│ Trusts plaintext    │     │ Verifies tag first  │
│ Malleable           │     │ Tamper-evident      │
└─────────────────────┘     └─────────────────────┘
```