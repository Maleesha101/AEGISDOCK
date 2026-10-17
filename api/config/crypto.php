<?php

return [
    'mode' => env('AEGISDOCK_MODE', 'vulnerable'),

    // Runtime-only local secret file. The vulnerable image accidentally retains
    // a historical copy in an earlier layer; the secure image excludes it.
    'key_file' => env('CRYPTO_KEY_FILE', '/run/secrets/terminal-gateway.env'),
    'key' => env('CRYPTO_KEY'),
    'iv' => env('CRYPTO_IV'),

    // Secure mode reads a separate base64-encoded 32-byte key from a runtime
    // mounted secret file. No default key is stored in source or image.
    'secure_key_file' => env('CRYPTO_SECURE_KEY_FILE', '/run/secrets/crypto_secure_key'),
    'secure_key' => env('CRYPTO_SECURE_KEY'),
];
