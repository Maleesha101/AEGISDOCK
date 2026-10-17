<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\TerminalController;
use Illuminate\Support\Facades\Route;

// Health is intentionally public so Compose/CI can verify the API is up.
Route::get('/health', static fn () => response()->json([
    'service' => 'aegisdock-api',
    'status' => 'ok',
    'mode' => config('app.env') === 'production' ? 'production' : env('AEGISDOCK_MODE', 'vulnerable'),
]));

// Only login is public. Every operational endpoint requires a valid Sanctum token.
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/terminals', [TerminalController::class, 'index']);
    Route::get('/terminals/{terminal}', [TerminalController::class, 'show']);

    Route::get('/containers', [ContainerController::class, 'index']);
    Route::get('/containers/{container}', [ContainerController::class, 'show']);
    Route::post('/containers/{container}/inspect', [ContainerController::class, 'inspect']);
    Route::post('/containers/{container}/hold', [ContainerController::class, 'hold']);
    Route::post('/containers/{container}/release', [ContainerController::class, 'release']);

    Route::get('/authorization/artifact', [AuthorizationController::class, 'getArtifact']);
    Route::get('/audit', [AuditController::class, 'index']);
    Route::get('/debug/authorization/{id}', [AuthorizationController::class, 'debug']);
});
