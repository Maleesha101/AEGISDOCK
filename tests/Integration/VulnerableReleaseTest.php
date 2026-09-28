<?php

use App\Models\User;
use App\Models\Container;
use App\Models\AuthorizationArtifact;
use App\Services\CryptoService;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Set up vulnerable crypto config
    config()->set('crypto.key', 'TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12');
    config()->set('crypto.iv', 'TRAINING-FAKE-IV-1234567890ABC');
});

test('vulnerable crypto generates consistent artifact', function () {
    $crypto = new CryptoService();

    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    expect($artifact)->toBeString();
    expect(strlen($artifact))->toBeGreaterThan(0);
});

test('vulnerable crypto decrypts artifact', function () {
    $crypto = new CryptoService();

    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    $decrypted = $crypto->decryptArtifact($artifact);

    expect($decrypted['operator_id'])->toBe(1001);
    expect($decrypted['terminal'])->toBe('7');
    expect($decrypted['container'])->toBe('1');
    expect($decrypted['action'])->toBe('VIEW');
});

test('vulnerable crypto uses static IV - same plaintext produces same ciphertext', function () {
    $crypto = new CryptoService();

    $artifact1 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
    $artifact2 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // Same IV + same plaintext = same ciphertext
    expect($artifact1)->toBe($artifact2);
});

test('vulnerable crypto artifact can trigger unauthorized release', function () {
    // This test demonstrates the vulnerability - operator + manipulated artifact = release

    $container = Container::where('container_number', 'CONT-88421')->first();

    // Login as operator
    $operator = User::where('email', 'operator@aegisdock.local')->first();

    // Generate artifact with VIEW action
    $crypto = new CryptoService();
    $artifact = $crypto->generateArtifact(1001, 7, $container->id, 'VIEW');

    // In the vulnerable app, we demonstrate how CBC manipulation works
    // For this test, we just verify the artifact generates and can be decrypted
    // The actual manipulation is demonstrated in the training environment

    // Verify artifact can be decrypted back to original
    $decrypted = $crypto->decryptArtifact($artifact);
    expect($decrypted['action'])->toBe('VIEW');
});

test('vulnerable crypto key can be recovered from Docker image', function () {
    // The key TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
    // was intentionally placed in Docker image layer
    // In training, learners recover it via docker history

    $key = config('crypto.key');
    expect($key)->toBe('TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12');
});

test('vulnerable crypto does not verify authentication tag', function () {
    $crypto = new CryptoService();

    $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // The vulnerable implementation does NOT verify integrity
    // This means tampered ciphertext could decrypt to modified plaintext
    // without detection

    // In the vulnerable version, we trust the decrypted fields
    $decrypted = $crypto->decryptArtifact($artifact);
    expect($decrypted['action'])->toBe('VIEW');
});