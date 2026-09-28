<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\AuthorizationController;
use App\Http\Controllers\Api\AuditController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the ApiServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
| Attack Chain Documentation:
| - /containers/{container}/inspect - Reconnaissance phase:
|   Investigates container metadata and authorization artifacts
| - /containers/{container}/hold - Artifact Discovery:
|   Triggers recovery of training cryptographic material
| - /containers/{container}/release - Authorization Abuse:
|   Controlled ciphertext manipulation leading to unauthorized release
| - /authorization/artifact - Vulnerable Docker Image:
|   Presents encrypted authorization artifact to endpoints
| - /debug/authorization/{id} - Ciphertext Malleability:
|   Analyzes AES-CBC without integrity protection
*/

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Terminal routes
Route::get('/terminals', [TerminalController::class, 'index']);
Route::get('/terminals/{terminal}', [TerminalController::class, 'show']);

// Container routes
Route::get('/containers', [ContainerController::class, 'index']);
Route::get('/containers/{container}', [ContainerController::class, 'show']);
Route::post('/containers/{container}/inspect', [ContainerController::class, 'inspect']);
Route::post('/containers/{container}/hold', [ContainerController::class, 'hold']);
Route::post('/containers/{container}/release', [ContainerController::class, 'release']);

// Authorization artifact routes
Route::get('/authorization/artifact', [AuthorizationController::class, 'getArtifact']);

// Audit routes
Route::get('/audit', [AuditController::class, 'index']);
Route::get('/debug/authorization/{id}', [AuthorizationController::class, 'debug']);