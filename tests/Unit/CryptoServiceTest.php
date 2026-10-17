<?php

namespace Tests\Unit;

use App\Services\CryptoService;
use Tests\TestCase;

class CryptoServiceTest extends TestCase
{
    public function test_vulnerable_artifact_round_trips_expected_training_fields(): void
    {
        $crypto = app(CryptoService::class);

        $artifact = $crypto->generateArtifact(1001, 7, 1, 'VIEW');
        $decoded = $crypto->decryptArtifact($artifact);

        $this->assertSame(1001, $decoded['operator_id']);
        $this->assertSame('7', $decoded['terminal']);
        $this->assertSame('1', $decoded['container']);
        $this->assertSame('VIEW', $decoded['action']);
        $this->assertGreaterThan(0, $decoded['issued_at']);
        $this->assertGreaterThan($decoded['issued_at'], $decoded['expires_at']);
    }
}
