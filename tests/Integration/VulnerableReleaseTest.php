<?php

namespace Tests\Integration;

use App\Models\Container;
use App\Models\User;
use App\Services\CryptoService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VulnerableReleaseTest extends TestCase
{
    public function test_unauthenticated_release_is_rejected(): void
    {
        $response = $this->postJson('/api/containers/CONT-88421/release');

        $response->assertUnauthorized();
    }

    public function test_operator_artifact_action_is_trusted_in_vulnerable_mode(): void
    {
        $this->seed();

        $operator = User::where('email', 'operator@aegisdock.local')->firstOrFail();
        $container = Container::where('container_number', 'CONT-88421')->firstOrFail();
        $terminal = $container->terminal;

        Sanctum::actingAs($operator);

        // The test constructs a RELEASE artifact directly to exercise the
        // vulnerable authorization decision. The learner's path to that
        // artifact is documented separately in the lab exercise.
        $artifact = app(CryptoService::class)->generateArtifact(
            $operator->id,
            $terminal->id,
            $container->id,
            'RELEASE'
        );

        $response = $this->postJson(
            '/api/containers/CONT-88421/release',
            ['reason' => 'local training test'],
            ['X-Terminal-Authorization' => $artifact]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'RELEASED');

        $this->assertDatabaseHas('containers', [
            'id' => $container->id,
            'status' => 'RELEASED',
        ]);
    }

    public function test_view_artifact_does_not_authorize_release(): void
    {
        $this->seed();

        $operator = User::where('email', 'operator@aegisdock.local')->firstOrFail();
        $container = Container::where('container_number', 'CONT-88421')->firstOrFail();
        $terminal = $container->terminal;
        Sanctum::actingAs($operator);

        $artifact = app(CryptoService::class)->generateArtifact(
            $operator->id,
            $terminal->id,
            $container->id,
            'VIEW'
        );

        $this->postJson(
            '/api/containers/CONT-88421/release',
            ['reason' => 'local training test'],
            ['X-Terminal-Authorization' => $artifact]
        )->assertForbidden();

        $this->assertDatabaseHas('containers', [
            'id' => $container->id,
            'status' => 'READY',
        ]);
    }
}
