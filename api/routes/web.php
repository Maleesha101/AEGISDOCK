<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\AuditController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here you can register web routes for your application. These routes
| are loaded by the RouteServiceProvider within a group which
| is assigned the "web" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {
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

Route::post('/login', [AuthController::class, 'login']);