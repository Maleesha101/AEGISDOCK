<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "syslog", "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Log Channel
        |--------------------------------------------------------------------------
        |
        | This channel logs security-relevant events for forensic analysis.
        | It intentionally logs cryptographic metadata (but NOT keys) to
        | help learners understand the attack surface.
        |
        */

        'audit' => [
            'driver' => 'single',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum log level to record.
    |
    */

    'level' => env('LOG_LEVEL', 'debug'),

];