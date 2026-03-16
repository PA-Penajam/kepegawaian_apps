<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kepegawaian\DokumenPegawaiController;
use App\Http\Controllers\Kepegawaian\HukumanDisiplinController;
use App\Http\Controllers\Kepegawaian\KeluargaController;
use App\Http\Controllers\Kepegawaian\PegawaiController;
use App\Http\Controllers\Kepegawaian\PenghargaanController;
use App\Http\Controllers\Kepegawaian\RiwayatJabatanController;
use App\Http\Controllers\Kepegawaian\RiwayatPendidikanController;
use App\Http\Controllers\Kepegawaian\SelfServiceController;
use App\Http\Controllers\Monitoring\MonitoringKenaikanPangkatController;
use App\Http\Controllers\Monitoring\MonitoringKgbController;
use App\Http\Controllers\Referensi\RefJenisDokumenController;
use App\Http\Controllers\Referensi\RefRoleController;
use App\Http\Controllers\Referensi\RefStatusKepegawaianController;
use App\Http\Controllers\Referensi\RefStatusPegawaiController;

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'verified', 'permission:pegawai.view,referensi.view,monitoring.view,rbac.manage'])->group(function () {
    Route::resource('kepegawaian/pegawai', PegawaiController::class)
        ->names('kepegawaian.pegawai');

    Route::get('kepegawaian/monitoring/kgb', [MonitoringKgbController::class, 'index'])
        ->name('monitoring.kgb.index');

    Route::get('kepegawaian/monitoring/kenaikan-pangkat', [MonitoringKenaikanPangkatController::class, 'index'])
        ->name('monitoring.kenaikan-pangkat.index');

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

});

Route::middleware(['auth', 'verified', 'permission:pegawai.view,pegawai.create,pegawai.update,pegawai.delete'])
    ->prefix('kepegawaian')
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

Route::middleware(['auth', 'verified'])
    ->prefix('self-service')
    ->name('self-service.')
    ->group(function () {
        Route::get('/', [SelfServiceController::class, 'index'])->name('index');
        Route::get('/detail', [SelfServiceController::class, 'detail'])->name('detail');
    });

require __DIR__.'/settings.php';
