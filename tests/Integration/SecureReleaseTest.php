<?php

use App\Models\User;
use App\Models\Terminal;
use App\Models\Container;
use Database\Seeders\DatabaseSeeder;
use App\Services\SecureCryptoService;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class);

beforeEach(function () {
    // RefreshDatabase in the shared test case manages migrations/transactions.
    $this->seed(DatabaseSeeder::class);

    // Set up secure crypto for testing
    config()->set('crypto.mode', 'secure');
    config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
});

test('operator cannot release container even with valid artifact in secure mode', function () {
    // Login as operator
    $operator = User::where('email', 'operator@aegisdock.local')->first();
    Sanctum::actingAs($operator, ['*']);

    // Get test container
    $container = Container::where('container_number', 'CONT-88421')->first();
    $terminal = Terminal::where('code', 'T-07')->first();

    // Generate a valid authorization artifact using secure crypto
    $crypto = new SecureCryptoService();
    $artifact = $crypto->generateArtifact(
        $operator->id,
        $terminal->id,
        $container->id,
        'RELEASE'  // Try to fake it with RELEASE action
    );

    // Attempt to release with the artifact
    $response = $this->postJson(
        "/api/containers/{$container->container_number}/release",
        ['reason' => 'test release'],
        ['X-Terminal-Authorization' => $artifact]
    );

    // Should fail because operator role cannot release (server-side check primary)
    $response->assertStatus(403);
    $response->assertJson([
        'success' => false,
        'message' => 'Insufficient permissions - operator role cannot release containers'
    ]);

    // Container should still be READY
    $this->assertDatabaseHas('containers', [
        'id' => $container->id,
        'status' => 'READY'
    ]);
});

test('supervisor can release container without artifact in secure mode', function () {
    // Login as supervisor
    $supervisor = User::where('email', 'supervisor@aegisdock.local')->first();
    Sanctum::actingAs($supervisor, ['*']);

    // Get test container
    $container = Container::where('container_number', 'CONT-88421')->first();

    // Attempt to release WITHOUT artifact
    $response = $this->postJson(
        "/api/containers/{$container->container_number}/release",
        ['reason' => 'scheduled release']
    );

    // Should succeed because supervisor can release (server-side check)
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Container released successfully'
    ]);

    // Container should now be RELEASED
    $this->assertDatabaseHas('containers', [
        'id' => $container->id,
        'status' => 'RELEASED'
    ]);

    // Should have created a container event
    $this->assertDatabaseHas('container_events', [
        'container_id' => $container->id,
        'action' => 'RELEASE'
    ]);
});

test('secure crypto rejects tampered ciphertext', function () {
    $crypto = new SecureCryptoService();

    // Generate valid artifact
    $validArtifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // Tamper with it
    $decoded = base64_decode($validArtifact);
    $tampered = $decoded;
    // Flip a bit in the ciphertext portion
    $lastIndex = strlen($tampered) - 1;
    $tampered[$lastIndex] = chr(ord($tampered[$lastIndex]) ^ 0x01);
    $tamperedArtifact = base64_encode($tampered);

    // Attempt to decrypt should fail
    $this->expectException(Exception::class);
    $crypto->decryptArtifact($tamperedArtifact);
});

test('secure crypto uses unique nonce per encryption', function () {
    $crypto = new SecureCryptoService();

    $artifact1 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
    $artifact2 = $crypto->generateArtifact(1001, 7, 1, 'VIEW');

    // Should be different due to unique nonce
    $this->assertNotEquals($artifact1, $artifact2);

    // But both should decrypt correctly
    $decrypted1 = $crypto->decryptArtifact($artifact1);
    $decrypted2 = $crypto->decryptArtifact($artifact2);

    $this->assertEquals($decrypted1['action'], $decrypted2['action']);
    $this->assertEquals($decrypted1['operator_id'], $decrypted2['operator_id']);
});

test('secure crypto validates artifact expiration', function () {
    $crypto = new SecureCryptoService();

    // Create an expired artifact manually
    $plaintext = json_encode([
        'operator_id' => 1001,
        'terminal' => '7',
        'container' => '1',
        'action' => 'VIEW',
        'issued_at' => time() - 7200,  // 2 hours ago
        'expires_at' => time() - 3600, // 1 hour ago (expired)
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $nonce = random_bytes(12);
    $key = base64_decode(config('crypto.secure_key'));
    $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
        $plaintext,
        '',
        $nonce,
        $key
    );
    $expiredArtifact = base64_encode($nonce . $ciphertext);

    // Attempt to decrypt should fail
    $this->expectException(Exception::class);
    $crypto->decryptArtifact($expiredArtifact);
});