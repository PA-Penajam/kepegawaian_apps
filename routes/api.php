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
| Dilindungi oleh 3-layer security:
| 1. HTTPS (transport layer)
| 2. Sanctum Token (authentication)
| 3. HMAC-SHA256 Signature (request integrity)
|
*/

Route::middleware(['auth:sanctum', 'verify.hmac'])->prefix('v1')->group(function () {
    // Single pegawai lookup by NIP (18 digits)
    Route::get('pegawai/{nip}', [PegawaiApiController::class, 'show'])
        ->where('nip', '^\d{18}$');

    // Batch lookup (nip[]) atau search (search + status)
    Route::get('pegawai', [PegawaiApiController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'iam.signature'])->prefix('v1/iam')->group(function () {
    Route::get('validate', [IamController::class, 'validate']);
    Route::get('check', [IamController::class, 'check']);
    Route::post('logout', [IamController::class, 'logout']);
});

Route::middleware(['iam.signature'])->prefix('v1/iam')->group(function () {
    Route::post('exchange-code', [IamController::class, 'exchangeCode']);
});
