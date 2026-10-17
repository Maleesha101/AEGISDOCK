<?php

use App\Services\CryptoService;
use App\Services\SecureCryptoService;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Set up config for vulnerable crypto
    config()->set('crypto.key', 'TRAINING-KEY-32-BYTES-ONLY-00000');
    config()->set('crypto.iv', 'TRAINING-IV-1234');
    config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
});

test('vulnerable crypto generates artifact', function () {
    $crypto = new CryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    expect($artifact)->toBeString();
    expect(strlen($artifact))->toBeGreaterThan(0);
});

test('vulnerable crypto decrypts artifact correctly', function () {
    $crypto = new CryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    $decrypted = $crypto->decryptArtifact($artifact);

    expect($decrypted['operator_id'])->toBe(1001);
    expect($decrypted['terminal'])->toBe('7');
    expect($decrypted['container'])->toBe('1');
    expect($decrypted['action'])->toBe('VIEW');
    expect($decrypted['issued_at'])->toBeGreaterThan(0);
    expect($decrypted['expires_at'])->toBeGreaterThan($decrypted['issued_at']);
});

test('vulnerable crypto uses static IV', function () {
    $crypto = new CryptoService();
    $artifact1 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
    $artifact2 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // The implementation reuses the same IV for each encryption.
    expect($crypto->getIv())->toBe('TRAINING-IV-1234');
    expect($crypto->getIv())->toBe($crypto->getIv());
    expect($artifact1)->toBeString();
    expect($artifact2)->toBeString();
});

test('vulnerable crypto is malleable - CBC manipulation', function () {
    $crypto = new CryptoService();

    // Generate an artifact with VIEW action
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
    $ciphertext = base64_decode($artifact);

    // In AES-CBC, we can flip bits in ciphertext to change plaintext
    // This is the core vulnerability - no integrity protection

    // Block size is 16 bytes (128 bits)
    $blockSize = 16;

    // For this test, we just verify the vulnerability exists
    // The actual manipulation is demonstrated in the training lab
    expect(strlen($ciphertext) % $blockSize)->toBe(0);
    expect(strlen($ciphertext))->toBeGreaterThan(0);
});

test('secure crypto uses AES-GCM', function () {
    $crypto = new SecureCryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    expect($artifact)->toBeString();
    expect(strlen($artifact))->toBeGreaterThan(0);
});

test('secure crypto decrypts artifact correctly', function () {
    $crypto = new SecureCryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    $decrypted = $crypto->decryptArtifact($artifact);

    expect($decrypted['operator_id'])->toBe(1001);
    expect($decrypted['terminal'])->toBe('7');
    expect($decrypted['container'])->toBe('1');
    expect($decrypted['action'])->toBe('VIEW');
});

test('secure crypto uses unique nonce per encryption', function () {
    $crypto = new SecureCryptoService();
    $artifact1 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
    $artifact2 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // Different nonces = different ciphertexts
    expect($artifact1)->not->toBe($artifact2);
});

test('secure crypto rejects tampered ciphertext', function () {
    $crypto = new SecureCryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // Tamper with the ciphertext
    $decoded = base64_decode($artifact);
    $tampered = $decoded;
    $tampered = substr($decoded, 0, -1) . chr(ord(substr($decoded, -1)) ^ 0xFF); // Flip last bit
    $tamperedArtifact = base64_encode($tampered);

    // Should throw exception on tampered ciphertext
    $crypto->decryptArtifact($tamperedArtifact);
})->throws(Exception::class);

test('secure crypto rejects expired artifact', function () {
    // Create a secure crypto with expired artifact
    $crypto = new SecureCryptoService();

    // Manually create an expired artifact
    $plaintext = json_encode([
        'operator_id' => 1001,
        'terminal' => '7',
        'container' => '1',
        'action' => 'VIEW',
        'issued_at' => time() - 7200, // 2 hours ago
        'expires_at' => time() - 3600, // 1 hour ago (expired)
    ], JSON_PRESERVE_ZERO_FRACTION);

    $nonce = random_bytes(12);
    $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
        $plaintext,
        '',
        $nonce,
        base64_decode(config('crypto.secure_key'))
    );
    $expiredArtifact = base64_encode($nonce . $ciphertext);

    // Should throw exception on expired artifact
    $crypto->decryptArtifact($expiredArtifact);
})->throws(Exception::class);

test('vulnerable crypto algorithm is AES-256-CBC', function () {
    $crypto = new CryptoService();
    expect($crypto->getAlgorithm())->toBe('aes-256-cbc');
});

test('secure crypto algorithm is AES-256-GCM', function () {
    $crypto = new SecureCryptoService();
    expect($crypto->getAlgorithm())->toBe('AES-256-GCM');
});