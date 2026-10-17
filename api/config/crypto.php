<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | The encryption key used for AES-256-CBC encryption of authorization
    | artifacts. In the vulnerable version, this is exposed through Docker
    | image layers.
    |
    | In training, learners must recover this from the Docker image history.
    |
    */
    'key' => env('CRYPTO_KEY', 'TRAINING-KEY-32-BYTES-ONLY-00000'),

    /*
    |--------------------------------------------------------------------------
    | Initialization Vector (IV)
    |--------------------------------------------------------------------------
    |
    | Static IV used for all encryptions - this is intentionally vulnerable
    | and allows ciphertext manipulation attacks.
    |
    | This should be recovered along with the key from Docker image layers.
    */
    'iv' => env('CRYPTO_IV', 'TRAINING-IV-1234'),

];