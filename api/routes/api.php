<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\AuditController;

// Authentication is the only unauthenticated application route.
// Throttle login attempts to avoid adding an unrelated brute-force weakness.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

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
    Route::get('/debug/authorization/{id}', [AuthorizationController::class, 'debug']);

    Route::get('/audit', [AuditController::class, 'index']);
});
