<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Container;
use App\Models\AuthorizationArtifact;

/**
 * Test the vulnerable container release flow.
 * 
 * This test validates the attack chain from reconnaissance
 * through unauthorized container release.
 */
class VulnerableReleaseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_vulnerable_container_release_without_auth()
    {
        // Arrange - create a container with authorization artifact
        $container = Container::factory()->create([
            'status' => 'held',
        ]);

        $artifact = AuthorizationArtifact::factory()->create([
            'container_id' => $container->id,
            'valid' => true,
        ]);

        // Act - attempt release without proper authorization
        $response = $this->postJson('/api/containers/' . $container->id . '/release');

        // Assert - should be forbidden in secure mode,
        // but vulnerable mode allows the release
        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'container_id']);
    }

    /** @test */
    public function test_crypto_analysis_endpoint_returns_aes_info()
    {
        // Arrange
        $container = Container::factory()->create();

        // Act
        $response = $this->getJson('/api/crypto/analysis');

        // Assert - should return cryptographic material info
        $response->assertStatus(200)
            ->assertSee('aes');
    }
}