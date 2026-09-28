<?php

return [
    'name' => 'AegisDock',
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'assetUrl' => env('ASSET_URL'),
    'version' => '1.0.0-training',
    'provider' => [
        'App\Providers\AppServiceProvider',
    ],
    'aliases' => [
        'App' => App\Facades\App::class,
    ],
    'providers' => [
        // Laravel service providers
        Illuminate\Foundation\Providers\BroadcastServiceProvider::class,
        Illuminate\Foundation\Providers\BusServiceProvider::class,
        Illuminate\Foundation\Providers\EventServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleServiceProvider::class,
        Illuminate\Foundation\Providers\GateServiceProvider::class,
        Illuminate\Foundation\Providers\OctaneServiceProvider::class,
        Illuminate\Foundation\Providers\TaskServiceProvider::class,
        Illuminate\Foundation\Providers\HorizonServiceProvider::class,
        Illuminate\Foundation\Providers\PassportServiceProvider::class,
        Laravel\Sanctum\SanctumServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,
    ],
];