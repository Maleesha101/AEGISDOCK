<?php

namespace Tests\Integration;

use App\Models\FlagSubmission;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class FlagSubmissionTest extends TestCase
{
    private string $flagFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->flagFile = tempnam(sys_get_temp_dir(), 'aegisdock-flags-');
        file_put_contents($this->flagFile, "FLAG_01=AEGIS{test-flag-01}\nFLAG_10=AEGIS{test-flag-10}\n");
        config()->set('flags.file', $this->flagFile);
    }

    protected function tearDown(): void
    {
        if (isset($this->flagFile) && is_file($this->flagFile)) {
            unlink($this->flagFile);
        }

        parent::tearDown();
    }

    private function operatorToken(): string
    {
        $response = $this->postJson('/api/login', [
            'email' => 'operator@aegisdock.local',
            'password' => 'Operator123!',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    public function test_valid_flag_is_recorded_once_per_user(): void
    {
        $token = $this->operatorToken();

        $this->withToken($token)->postJson('/api/flags/submit', [
            'code' => 'FLAG-01',
            'flag' => 'AEGIS{test-flag-01}',
        ])->assertOk()->assertJsonPath('data.code', 'FLAG-01');

        $this->assertDatabaseHas('flag_submissions', [
            'flag_code' => 'FLAG-01',
        ]);

        $this->withToken($token)->postJson('/api/flags/submit', [
            'code' => 'FLAG-01',
            'flag' => 'AEGIS{test-flag-01}',
        ])->assertOk()->assertJsonPath('data.already_submitted', true);

        $this->assertSame(1, FlagSubmission::where('flag_code', 'FLAG-01')->count());
    }

    public function test_invalid_flag_is_rejected(): void
    {
        $token = $this->operatorToken();

        $this->withToken($token)->postJson('/api/flags/submit', [
            'code' => 'FLAG-01',
            'flag' => 'AEGIS{wrong}',
        ])->assertUnprocessable();
    }

    public function test_secure_verification_returns_stage_flag_only_in_secure_mode(): void
    {
        config()->set('crypto.mode', 'secure');

        $token = $this->operatorToken();

        $this->withToken($token)->getJson('/api/security/verification')
            ->assertOk()
            ->assertJsonPath('data.mode', 'secure')
            ->assertJsonPath('data.flag', 'AEGIS{test-flag-10}');
    }
}
