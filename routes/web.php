<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BerkasChecklist\ChecklistTemplateController;
use App\Http\Controllers\Cuti\ApprovalController;
use App\Http\Controllers\Cuti\AuditController;
use App\Http\Controllers\Cuti\PdfController as CutiPdfController;
use App\Http\Controllers\Cuti\PengajuanController;
use App\Http\Controllers\Cuti\SaldoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Iam\AplikasiController;
use App\Http\Controllers\Iam\PermissionController;
use App\Http\Controllers\Iam\RoleController;
use App\Http\Controllers\Iam\UserAksesController;
use App\Http\Controllers\Kepegawaian\ApprovalPengajuanPerubahanDataController;
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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Referensi\RefJenisDokumenController;
use App\Http\Controllers\Referensi\RefRoleController;
use App\Http\Controllers\Referensi\RefStatusKepegawaianController;
use App\Http\Controllers\Referensi\RefStatusPegawaiController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\UsulanKenaikanPangkat\ApprovalController as KenaikanPangkatApprovalController;
use App\Http\Controllers\UsulanKenaikanPangkat\SkAdminController as KenaikanPangkatSkAdminController;
use App\Http\Controllers\UsulanKenaikanPangkat\UsulanKenaikanPangkatController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// SSO Login routes
Route::get('/sso/login', [SsoController::class, 'login'])->name('sso.login');
Route::middleware('auth')->get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Notification routes
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Self-service routes
    Route::prefix('self-service')
        ->name('self-service.')
        ->group(function () {
            Route::get('/', [SelfServiceController::class, 'index'])->name('index');
            Route::get('/detail', [SelfServiceController::class, 'detail'])->name('detail');

        });

    // Cuti PDF
    Route::get('cuti/pengajuan/{id}/pdf', [CutiPdfController::class, 'show'])->name('cuti.pengajuan.pdf');
});

Route::middleware(['auth', 'verified', 'iam.permission:iam-manage'])->group(function () {
    Route::get('activity-log', [ActivityLogController::class, 'index'])
        ->name('activity-log.index');

    // IAM management routes
    Route::prefix('iam')
        ->name('iam.')
        ->group(function () {
            Route::resource('aplikasi', AplikasiController::class)
                ->except(['create']);
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

    Route::resource('admin/checklist-template', ChecklistTemplateController::class)
        ->parameters(['checklist-template' => 'template'])
        ->names('admin.checklist-template')
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

            // Pengajuan perubahan data - validator inbox
            Route::prefix('pengajuan')
                ->name('pengajuan.')
                ->middleware('iam.permission:pengajuan-perubahan.validate')
                ->group(function () {
                    Route::get('/', [ApprovalPengajuanPerubahanDataController::class, 'index'])->name('index');
                    Route::get('/{pengajuan}', [ApprovalPengajuanPerubahanDataController::class, 'show'])->name('show');
                    Route::post('/{pengajuan}/approve', [ApprovalPengajuanPerubahanDataController::class, 'approve'])->name('approve');
                    Route::post('/{pengajuan}/reject', [ApprovalPengajuanPerubahanDataController::class, 'reject'])->name('reject');
                });
        });
});

// === Cuti (Leave) routes ===
Route::middleware(['auth', 'verified'])->prefix('cuti')->name('cuti.')->group(function () {
    Route::get('/saya', [SaldoController::class, 'myDashboard'])->name('saya');
    Route::get('/pengajuan/baru', [PengajuanController::class, 'create'])->name('pengajuan.create');
    Route::post('/pengajuan', [PengajuanController::class, 'store'])->name('pengajuan.store');
    Route::get('/pengajuan/{id}', [PengajuanController::class, 'show'])->name('pengajuan.show');
    Route::get('/inbox', [ApprovalController::class, 'inbox'])->name('inbox');
    Route::post('/pengajuan/{id}/verify', [ApprovalController::class, 'verify'])->name('pengajuan.verify');
    Route::post('/pengajuan/{id}/approve-atasan', [ApprovalController::class, 'approveAtasan'])->name('pengajuan.approve-atasan');
    Route::post('/pengajuan/{id}/approve-pejabat', [ApprovalController::class, 'approvePejabat'])->name('pengajuan.approve-pejabat');
    Route::post('/pengajuan/{id}/reject', [ApprovalController::class, 'reject'])->name('pengajuan.reject');
    Route::post('/pengajuan/{id}/cancel', [ApprovalController::class, 'cancel'])->name('pengajuan.cancel');
    Route::post('/pengajuan/{id}/reassign-approver', [ApprovalController::class, 'reassign'])->middleware('permission:cuti.pengajuan.reassign')->name('pengajuan.reassign');
});

Route::middleware(['auth', 'verified'])->prefix('kenaikan-pangkat')->name('kenaikan-pangkat.')->group(function () {
    Route::get('usulan/{usulan}/activity', [UsulanKenaikanPangkatController::class, 'activity'])->name('usulan.activity');
    Route::resource('usulan', UsulanKenaikanPangkatController::class);
    Route::post('usulan/{usulan}/submit', [UsulanKenaikanPangkatController::class, 'submit'])->name('usulan.submit');
    Route::post('usulan/{usulan}/batalkan', [UsulanKenaikanPangkatController::class, 'batalkan'])->name('usulan.batalkan');
    Route::get('approval/inbox', [KenaikanPangkatApprovalController::class, 'inbox'])->name('approval.inbox');
    Route::post('approval/{usulan}/verifikasi-kasubbag', [KenaikanPangkatApprovalController::class, 'verifikasiKasubbag'])->name('approval.verifikasi-kasubbag');
    Route::post('approval/{usulan}/verifikasi-sekretaris', [KenaikanPangkatApprovalController::class, 'verifikasiSekretaris'])->name('approval.verifikasi-sekretaris');
    Route::post('approval/{usulan}/tanda-tangan-ketua', [KenaikanPangkatApprovalController::class, 'tandaTanganKetua'])->name('approval.tanda-tangan-ketua');
    Route::post('approval/{usulan}/kirim-biro', [KenaikanPangkatApprovalController::class, 'kirimBiro'])->name('approval.kirim-biro');
    Route::post('approval/{usulan}/minta-perbaikan', [KenaikanPangkatApprovalController::class, 'mintaPerbaikan'])->name('approval.minta-perbaikan');
    Route::post('approval/{usulan}/tolak', [KenaikanPangkatApprovalController::class, 'tolak'])->name('approval.tolak');
    Route::get('admin-sk', [KenaikanPangkatSkAdminController::class, 'index'])->name('admin-sk.index');
    Route::post('admin-sk/{usulan}/upload-sk', [KenaikanPangkatSkAdminController::class, 'uploadSk'])->name('admin-sk.upload-sk');
    Route::get('admin-sk/{usulan}/download-sk', [KenaikanPangkatSkAdminController::class, 'downloadSk'])->name('admin-sk.download-sk');
    Route::get('admin-sk/pdf/{pdf}/download', [KenaikanPangkatSkAdminController::class, 'downloadSuratPengantar'])->name('admin-sk.download-surat-pengantar');
});

Route::middleware(['auth', 'verified', 'permission:cuti.saldo.view-all'])->prefix('admin/cuti')->name('admin.cuti.')->group(function () {
    Route::get('/saldo', [SaldoController::class, 'adminIndex'])->name('saldo.index');
    Route::get('/saldo/init', [SaldoController::class, 'adminInit'])->name('saldo.init');
    Route::post('/saldo/init', [SaldoController::class, 'adminInitStore'])->name('saldo.init.store');
    Route::post('/saldo/adjust', [SaldoController::class, 'adminAdjust'])->middleware('permission:cuti.saldo.adjust')->name('saldo.adjust');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
});

require __DIR__.'/settings.php';
