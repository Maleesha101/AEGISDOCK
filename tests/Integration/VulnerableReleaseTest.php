<?php

namespace Tests\Integration;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class VulnerableReleaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function tokenFor(string $email, string $password): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        return $response->json('token');
    }

    public function test_operational_endpoints_require_authentication(): void
    {
        $this->getJson('/api/containers')->assertUnauthorized();
    }

    public function test_operator_can_login_and_read_own_profile(): void
    {
        $token = $this->tokenFor('operator@aegisdock.local', 'Operator123!');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'operator@aegisdock.local')
            ->assertJsonPath('user.role.name', 'operator');
    }

    public function test_operator_cannot_release_container_without_artifact(): void
    {
        $token = $this->tokenFor('operator@aegisdock.local', 'Operator123!');

        $this->withToken($token)
            ->postJson('/api/containers/CONT-88421/release', [
                'reason' => 'authorization boundary test',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('containers', [
            'container_number' => 'CONT-88421',
            'status' => 'READY',
        ]);
    }

    public function test_supervisor_can_release_simulated_container(): void
    {
        $token = $this->tokenFor('supervisor@aegisdock.local', 'Supervisor123!');

        $this->withToken($token)
            ->postJson('/api/containers/CONT-88421/release', [
                'reason' => 'authorized training release',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('containers', [
            'container_number' => 'CONT-88421',
            'status' => 'RELEASED',
        ]);
    }
}
