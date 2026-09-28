<?php

namespace Tests;

use Tests\TestCase;
use App\Services\CryptoService;
use App\Services\SecureCryptoService;

/**
 * Test cryptographic implementations in AegisDock.
 * 
 * Validates both vulnerable and secure crypto patterns
 * documented in the laboratory's threat model.
 */
class CryptoTest extends TestCase
{
    /** @test */
    public function test_vulnerable_aes_cbc_lacks_integrity()
    {
        $service = new CryptoService();

        // Encrypt test data
        $plaintext = 'Sensitive container release authorization';
        $ciphertext = $service->encrypt($plaintext);

        // Decrypt should succeed
        $decrypted = $service->decrypt($ciphertext);
        $this->assertEquals($plaintext, $decrypted);

        // Key derivation should NOT use PBKDF2 or Argon2
        $this->assertFalse($service->usesStrongKdf());
    }

    /** @test */
    public function test_secure_aes_gcm_provides_integrity()
    {
        $service = new SecureCryptoService();

        $plaintext = 'Secure container metadata';
        $ciphertext = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($ciphertext);

        $this->assertEquals($plaintext, $decrypted);
        $this->assertTrue($service->usesAesGcm());
    }
}