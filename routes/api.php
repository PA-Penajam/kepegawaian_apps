<?php

use App\Http\Controllers\Api\Cuti\PengajuanController as CutiPengajuanController;
use App\Http\Controllers\Api\Cuti\SaldoController as CutiSaldoController;
use App\Http\Controllers\Api\IamController;
use App\Http\Controllers\Api\PegawaiApiController;
use App\Http\Controllers\Api\UsulanKenaikanPangkat\UsulanKenaikanPangkatApiController;
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

// Cuti API — throttle 60 req/menit
Route::middleware(['auth:sanctum', 'verify.hmac', 'throttle:60,1'])
    ->prefix('cuti')
    ->name('api.cuti.')
    ->group(function () {
        Route::get('/pengajuan', [CutiPengajuanController::class, 'index'])->name('pengajuan.index');
        Route::get('/pengajuan/{id}', [CutiPengajuanController::class, 'show'])->name('pengajuan.show');
        Route::get('/saldo/{nip}', [CutiSaldoController::class, 'show'])->name('saldo.show');
        Route::get('/saldo/{nip}/ledger', [CutiSaldoController::class, 'ledger'])->name('saldo.ledger');
    });

Route::prefix('kenaikan-pangkat')
    ->name('api.kenaikan-pangkat.')
    ->middleware(['auth:sanctum', 'abilities:app:kepegawaian', 'verify.hmac', 'throttle:60,1'])
    ->group(function () {
        Route::get('usulan', [UsulanKenaikanPangkatApiController::class, 'index'])->name('usulan.index');
        Route::get('usulan/{usulan}', [UsulanKenaikanPangkatApiController::class, 'show'])->name('usulan.show');
        Route::get('stats', [UsulanKenaikanPangkatApiController::class, 'stats'])->name('stats');
    });

// Exchange code — throttle ketat 10 req/menit (endpoint sensitif SSO)
Route::middleware(['iam.signature', 'throttle:10,1'])
    ->prefix('v1/iam')
    ->group(function () {
        Route::post('exchange-code', [IamController::class, 'exchangeCode']);
    });
