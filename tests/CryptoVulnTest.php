<?php

namespace Tests;

use App\Services\CryptoService;
use App\Services\SecureCryptoService;
use Tests\TestCase;

class CryptoVulnTest extends TestCase
{
    public function test_vulnerable_artifact_uses_cbc_without_integrity(): void
    {
        $service = new CryptoService();
        $artifact = $service->generateArtifact(1001, 7, 1, 'VIEW');

        $this->assertSame('aes-256-cbc', $service->getAlgorithm());
        $this->assertSame('VIEW', $service->decryptArtifact($artifact)['action']);
    }

    public function test_secure_artifact_uses_authenticated_encryption(): void
    {
        config()->set('crypto.secure_key', base64_encode(random_bytes(32)));

        $service = new SecureCryptoService();
        $artifact = $service->generateArtifact(1001, 7, 1, 'VIEW');

        $this->assertSame('AES-256-GCM', $service->getAlgorithm());
        $this->assertSame('VIEW', $service->decryptArtifact($artifact)['action']);
    }
}
