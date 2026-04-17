<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Iam\AplikasiController;
use App\Http\Controllers\Iam\PermissionController;
use App\Http\Controllers\Iam\RoleController;
use App\Http\Controllers\Iam\UserAksesController;
use App\Http\Controllers\Kepegawaian\DokumenPegawaiController;
use App\Http\Controllers\Kepegawaian\HukumanDisiplinController;
use App\Http\Controllers\Kepegawaian\KeluargaController;
use App\Http\Controllers\Kepegawaian\PegawaiController;
use App\Http\Controllers\Kepegawaian\PenghargaanController;
use App\Http\Controllers\SelfService\PengajuanPerubahanDataController;
use App\Http\Controllers\Kepegawaian\RiwayatJabatanController;
use App\Http\Controllers\Kepegawaian\RiwayatPendidikanController;
use App\Http\Controllers\Kepegawaian\SelfServiceController;
use App\Http\Controllers\Monitoring\MonitoringKenaikanPangkatController;
use App\Http\Controllers\Monitoring\MonitoringKgbController;
use App\Http\Controllers\Referensi\RefJenisDokumenController;
use App\Http\Controllers\Referensi\RefRoleController;
use App\Http\Controllers\Referensi\RefStatusKepegawaianController;
use App\Http\Controllers\Referensi\RefStatusPegawaiController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// SSO Login routes
Route::get('/sso/login', [SsoController::class, 'login'])->name('sso.login');
Route::middleware('auth')->get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Self-service routes
    Route::prefix('self-service')
        ->name('self-service.')
        ->group(function () {
            Route::get('/', [SelfServiceController::class, 'index'])->name('index');
            Route::get('/detail', [SelfServiceController::class, 'detail'])->name('detail');

            // Pengajuan perubahan data routes
            Route::prefix('pengajuan')
                ->name('pengajuan.')
                ->group(function () {
                    Route::get('/', [PengajuanPerubahanDataController::class, 'index'])->name('index');
                    Route::get('/create', [PengajuanPerubahanDataController::class, 'create'])->name('create');
                    Route::post('/', [PengajuanPerubahanDataController::class, 'store'])->name('store');
                });
        });
});

Route::middleware(['auth', 'verified', 'iam.permission:iam-manage'])->group(function () {
    Route::get('activity-log', [ActivityLogController::class, 'index'])
        ->name('activity-log.index');

    // IAM management routes
    Route::prefix('iam')
        ->name('iam.')
        ->group(function () {
            Route::resource('aplikasi', AplikasiController::class)
                ->except(['create', 'edit']);
            Route::post('aplikasi/{aplikasi}/regenerate-key', [AplikasiController::class, 'regenerateKey'])
                ->name('aplikasi.regenerate-key');

            // Role & Permission (nested under aplikasi)
            Route::post('aplikasi/{aplikasi}/roles', [RoleController::class, 'store'])->name('aplikasi.roles.store');
            Route::put('aplikasi/{aplikasi}/roles/{role}', [RoleController::class, 'update'])->name('aplikasi.roles.update');
            Route::delete('aplikasi/{aplikasi}/roles/{role}', [RoleController::class, 'destroy'])->name('aplikasi.roles.destroy');
            Route::post('aplikasi/{aplikasi}/permissions', [PermissionController::class, 'store'])->name('aplikasi.permissions.store');
            Route::put('aplikasi/{aplikasi}/permissions/{permission}', [PermissionController::class, 'update'])->name('aplikasi.permissions.update');
            Route::delete('aplikasi/{aplikasi}/permissions/{permission}', [PermissionController::class, 'destroy'])->name('aplikasi.permissions.destroy');

            // User akses
            Route::get('users', [UserAksesController::class, 'index'])->name('users.index');
            Route::get('users/{user}/akses', [UserAksesController::class, 'show'])->name('users.akses');
            Route::post('users/{user}/akses', [UserAksesController::class, 'store'])->name('users.akses.store');
            Route::delete('users/{user}/akses/{role}', [UserAksesController::class, 'destroy'])->name('users.akses.destroy');
        });
});

Route::middleware(['auth', 'verified', 'iam.permission'])->group(function () {
    Route::resource('kepegawaian/pegawai', PegawaiController::class)
        ->names('kepegawaian.pegawai');

    Route::post('kepegawaian/pegawai/{pegawai}/foto', [PegawaiController::class, 'updateFoto'])
        ->name('kepegawaian.pegawai.foto.update');

    Route::get('kepegawaian/monitoring/kgb', [MonitoringKgbController::class, 'index'])
        ->name('monitoring.kgb.index');

    Route::get('kepegawaian/monitoring/kgb/export', [MonitoringKgbController::class, 'export'])
        ->name('monitoring.kgb.export');

    Route::get('kepegawaian/monitoring/kenaikan-pangkat', [MonitoringKenaikanPangkatController::class, 'index'])
        ->name('monitoring.kenaikan-pangkat.index');

    Route::get('kepegawaian/monitoring/kenaikan-pangkat/export', [MonitoringKenaikanPangkatController::class, 'export'])
        ->name('monitoring.kenaikan-pangkat.export');

    Route::resource('referensi/jenis-dokumen', RefJenisDokumenController::class)
        ->parameters(['jenis-dokumen' => 'jenisDokumen'])
        ->names('referensi.jenis-dokumen')
        ->except(['show']);

    Route::resource('referensi/status-kepegawaian', RefStatusKepegawaianController::class)
        ->parameters(['status-kepegawaian' => 'statusKepegawaian'])
        ->names('referensi.status-kepegawaian')
        ->except(['show']);
    Route::resource('referensi/status-pegawai', RefStatusPegawaiController::class)
        ->parameters(['status-pegawai' => 'statusPegawai'])
        ->names('referensi.status-pegawai')
        ->except(['show']);

    Route::resource('referensi/roles', RefRoleController::class)
        ->names('referensi.roles')
        ->except(['show']);

    // Nested kepegawaian routes
    Route::prefix('kepegawaian')
        ->name('kepegawaian.')
        ->group(function () {
            Route::resource(
                'pegawai.riwayat-diklat',
                'App\\Http\\Controllers\\Kepegawaian\\RiwayatDiklatController',
            )
                ->parameters([
                    'riwayat-diklat' => 'riwayatDiklat',
                ])
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.riwayat-jabatan', RiwayatJabatanController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.keluarga', KeluargaController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.penghargaan', PenghargaanController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.hukuman-disiplin', HukumanDisiplinController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.riwayat-pendidikan', RiwayatPendidikanController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('pegawai.dokumen', DokumenPegawaiController::class)
                ->parameters([
                    'dokumen' => 'dokumen',
                ])
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource(
                'pegawai.riwayat-pangkat',
                'App\\Http\\Controllers\\Kepegawaian\\RiwayatPangkatController',
            )
                ->only(['index', 'store', 'update', 'destroy']);
        });
});

require __DIR__.'/settings.php';
