<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up vulnerable crypto config for testing
        config()->set('crypto.key', 'TRAINING-KEY-32-BYTES-ONLY-00000');
        config()->set('crypto.iv', 'TRAINING-IV-1234');
        config()->set('crypto.secure_key', base64_encode(random_bytes(32)));
    }
}