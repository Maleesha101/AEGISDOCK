<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../api/bootstrap/app.php';
        $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up vulnerable crypto config for testing
        config()->set('crypto.key', 'TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12');
        config()->set('crypto.iv', 'TRAINING-FAKE-IV-1234567890ABC');
        config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
    }
}