<?php

use App\Http\Controllers\Api\IamController;
use App\Http\Controllers\Api\PegawaiApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Kepegawaian Integration
|--------------------------------------------------------------------------
|
| Route-route ini digunakan untuk integrasi dengan attendance-qr-system.
| Dilindungi oleh 4-layer security:
| 1. HTTPS (transport layer)
| 2. Sanctum Token (authentication)
| 3. HMAC-SHA256 Signature (request integrity)
| 4. Rate Limiting (throttle middleware - DDoS protection)
|
*/

// Pegawai API — throttle 60 req/menit
Route::middleware(['auth:sanctum', 'verify.hmac', 'throttle:60,1'])
    ->prefix('v1')
    ->group(function () {
        // Single pegawai lookup by NIP (18 digits)
        Route::get('pegawai/{nip}', [PegawaiApiController::class, 'show'])
            ->where('nip', '^\d{18}$');

        // Batch lookup (nip[]) atau search (search + status)
        Route::get('pegawai', [PegawaiApiController::class, 'index']);
    });

// IAM validate/check/logout — throttle 120 req/menit
Route::middleware(['auth:sanctum', 'iam.signature', 'throttle:120,1'])
    ->prefix('v1/iam')
    ->group(function () {
        Route::get('validate', [IamController::class, 'validate']);
        Route::get('check', [IamController::class, 'check']);
        Route::post('logout', [IamController::class, 'logout']);
    });

// Exchange code — throttle ketat 10 req/menit (endpoint sensitif SSO)
Route::middleware(['iam.signature', 'throttle:10,1'])
    ->prefix('v1/iam')
    ->group(function () {
        Route::post('exchange-code', [IamController::class, 'exchangeCode']);
    });
