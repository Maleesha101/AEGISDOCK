<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../api/bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up vulnerable crypto config for testing
        config()->set('crypto.key', random_bytes(32));
        config()->set('crypto.iv', random_bytes(16));
        config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
    }
}