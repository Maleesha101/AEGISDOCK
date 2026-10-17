<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => response()->json([
    'service' => 'aegisdock-api',
    'message' => 'AegisDock API is running. Use /api/health for health checks.',
]));
