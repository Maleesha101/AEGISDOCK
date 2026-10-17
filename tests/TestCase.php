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
        $app->make(\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up vulnerable crypto config for testing
        config()->set('crypto.key', 'AEGISDOCK-TRAINING-KEY-32-BYTES!');
        config()->set('crypto.iv', 'AEGIS-STATIC-IV!');
        config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
    }
}