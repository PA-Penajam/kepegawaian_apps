<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Notifications\Cuti\PengajuanDisetujui;
use App\Notifications\Cuti\PengajuanDitolak;
use App\Notifications\Cuti\PengajuanMenungguApproval;
use App\Services\Cuti\WorkflowService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);

    $app = IamApplication::where('slug', 'kepegawaian')->first();
    $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
    $cutiPermissions = IamPermission::where('iam_application_id', $app->id)
        ->where('group', 'cuti')
        ->pluck('id');
    $adminRole->permissions()->syncWithoutDetaching($cutiPermissions);
});

/**
 * Beri permission IAM kepada pegawai untuk keperluan testing.
 */
function grantNotifPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::firstOrCreate(['nama' => 'Test App', 'kode' => 'test', 'slug' => 'test-app'], ['url' => 'http://test.test']);
    $role = IamRole::firstOrCreate(
        ['kode' => 'test_role', 'iam_application_id' => $app->id],
        ['nama' => 'Test Role', 'slug' => 'test-role'],
    );
    $perm = IamPermission::firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug],
    );
    $role->permissions()->syncWithoutDetaching([$perm->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

/**
 * Buat pengajuan cuti untuk testing notifikasi.
 */
function buatPengajuanUntukNotifikasi(
    string $state,
    Pegawai $pemohon,
    Pegawai $atasan,
    Pegawai $pejabat,
): CutiPengajuan {
    return CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => $state,
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $pemohon->nip,
        'atasan_langsung_current_nip' => $atasan->nip,
        'pejabat_berwenang_current_nip' => $pejabat->nip,
    ]);
}

test('verify mengirim notifikasi ke atasan langsung', function () {
    Notification::fake();

    $petugas = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $atasan = Pegawai::factory()->create();
    $pejabat = Pegawai::factory()->create();

    $pengajuan = buatPengajuanUntukNotifikasi('DIAJUKAN', $pemohon, $atasan, $pejabat);

    app(WorkflowService::class)->verify($pengajuan->id, $petugas, 'OK');

    Notification::assertSentTo($atasan, PengajuanMenungguApproval::class, function ($notification, $channels) {
        expect($channels)->toBe(['database']);

        return true;
    });
});

test('approve atasan mengirim notifikasi ke pejabat berwenang', function () {
    Notification::fake();

    $pemohon = Pegawai::factory()->create();
    $atasan = Pegawai::factory()->admin()->create();
    $pejabat = Pegawai::factory()->create();

    $pengajuan = buatPengajuanUntukNotifikasi('DIVERIFIKASI', $pemohon, $atasan, $pejabat);

    grantNotifPermission($atasan, 'cuti.pengajuan.approve-langsung');

    app(WorkflowService::class)->approveAtasan($pengajuan->id, $atasan, 'Setuju');

    Notification::assertSentTo($pejabat, PengajuanMenungguApproval::class, function ($notification, $channels) {
        expect($channels)->toBe(['database']);

        return true;
    });
});

test('approve pejabat mengirim notifikasi ke pegawai pemohon', function () {
    Notification::fake();

    $pemohon = Pegawai::factory()->create();
    $atasan = Pegawai::factory()->create();
    $pejabat = Pegawai::factory()->admin()->create();

    $pengajuan = buatPengajuanUntukNotifikasi('DISETUJUI_ATASAN', $pemohon, $atasan, $pejabat);

    grantNotifPermission($pejabat, 'cuti.pengajuan.approve-pejabat');

    app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat, 'Disetujui');

    Notification::assertSentTo($pemohon, PengajuanDisetujui::class, function ($notification, $channels) {
        expect($channels)->toBe(['database']);

        return true;
    });
});

test('reject mengirim notifikasi ke pegawai pemohon', function () {
    Notification::fake();

    $pemohon = Pegawai::factory()->create();
    $atasan = Pegawai::factory()->admin()->create();
    $pejabat = Pegawai::factory()->create();

    $pengajuan = buatPengajuanUntukNotifikasi('DIVERIFIKASI', $pemohon, $atasan, $pejabat);

    grantNotifPermission($atasan, 'cuti.pengajuan.approve-langsung');

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $atasan, 'atasan_langsung', 'Tidak disetujui');

    Notification::assertSentTo($pemohon, PengajuanDitolak::class, function ($notification, $channels) {
        expect($channels)->toBe(['database']);

        return true;
    });
});
