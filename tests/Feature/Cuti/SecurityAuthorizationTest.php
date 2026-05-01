<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Cuti\WorkflowService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);

    // Berikan semua permission cuti ke admin role
    $app = IamApplication::where('slug', 'kepegawaian')->first();
    $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
    $cutiPermissions = IamPermission::where('iam_application_id', $app->id)
        ->where('group', 'cuti')
        ->pluck('id');
    $adminRole->permissions()->syncWithoutDetaching($cutiPermissions);
});

/**
 * Helper: buat pegawai dengan permission spesifik (bukan admin penuh).
 */
function buatPegawaiDenganPermission(array $permissionSlugs): Pegawai
{
    $pegawai = Pegawai::factory()->create();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    // Buat role sementara untuk test ini
    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'slug' => 'test-role-'.$pegawai->nip,
        'nama' => 'Test Role '.$pegawai->nip,
    ]);

    $permissionIds = IamPermission::where('iam_application_id', $app->id)
        ->whereIn('slug', $permissionSlugs)
        ->pluck('id');

    foreach ($permissionIds as $permId) {
        IamRolePermission::create([
            'iam_role_id' => $role->id,
            'iam_permission_id' => $permId,
        ]);
    }

    IamUserRole::create([
        'user_id' => $pegawai->id,
        'iam_role_id' => $role->id,
        'assigned_at' => now(),
    ]);

    return $pegawai;
}

/**
 * Helper: buat pengajuan CT dalam state tertentu.
 */
function buatPengajuanUntukTest(string $state, Pegawai $pemilik, array $extra = []): CutiPengajuan
{
    return CutiPengajuan::factory()->create(array_merge([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => $state,
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $pemilik->nip,
        'atasan_langsung_current_nip' => $pemilik->nip,
        'pejabat_berwenang_current_nip' => $pemilik->nip,
    ], $extra));
}

// =============================================================================
// FIX 1: PengajuanController::show authorization
// =============================================================================

test('user tanpa relasi tidak bisa melihat pengajuan orang lain via show endpoint', function () {
    $pemilik = buatPegawaiDenganPermission(['cuti.pengajuan.view-own']);
    $orangLain = buatPegawaiDenganPermission(['cuti.pengajuan.view-own']);

    $pengajuan = buatPengajuanUntukTest('DIAJUKAN', $pemilik);

    $this->actingAs($orangLain)
        ->get(route('cuti.pengajuan.show', $pengajuan->id))
        ->assertForbidden();
});

test('pemilik pengajuan dengan view-own bisa melihat pengajuannya sendiri', function () {
    $pemilik = buatPegawaiDenganPermission(['cuti.pengajuan.view-own']);
    $pengajuan = buatPengajuanUntukTest('DIAJUKAN', $pemilik);

    $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.show', $pengajuan->id))
        ->assertSuccessful();
});

test('atasan langsung dengan view-team bisa melihat pengajuan tim', function () {
    $pemilik = Pegawai::factory()->create();
    $atasan = buatPegawaiDenganPermission(['cuti.pengajuan.view-team']);

    $pengajuan = buatPengajuanUntukTest('DIAJUKAN', $pemilik, [
        'atasan_langsung_current_nip' => $atasan->nip,
    ]);

    $this->actingAs($atasan)
        ->get(route('cuti.pengajuan.show', $pengajuan->id))
        ->assertSuccessful();
});

test('admin dengan view-all bisa melihat pengajuan siapapun', function () {
    $pemilik = Pegawai::factory()->create();
    $admin = buatPegawaiDenganPermission(['cuti.pengajuan.view-all']);

    $pengajuan = buatPengajuanUntukTest('DIAJUKAN', $pemilik);

    $this->actingAs($admin)
        ->get(route('cuti.pengajuan.show', $pengajuan->id))
        ->assertSuccessful();
});

// =============================================================================
// FIX 2: API routes verify.hmac middleware
// =============================================================================

test('cuti API route tanpa HMAC signature ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Kirim request tanpa X-Signature header
    $response = $this->getJson('/api/cuti/pengajuan');
    $response->assertStatus(401);
});

// =============================================================================
// FIX 3a: cancelDraft — user dengan cancel-own tidak bisa cancel milik orang lain
// =============================================================================

test('cancelDraft: user cancel-own tidak bisa membatalkan pengajuan orang lain', function () {
    $pemilik = Pegawai::factory()->create();
    $orangLain = buatPegawaiDenganPermission(['cuti.pengajuan.cancel-own']);

    $pengajuan = buatPengajuanUntukTest('DRAFT', $pemilik);

    app(WorkflowService::class)->cancelDraft($pengajuan->id, $orangLain, 'coba batal');
})->throws(AuthorizationException::class);

test('cancelDraft: pemilik dengan cancel-own bisa membatalkan miliknya sendiri', function () {
    $pemilik = buatPegawaiDenganPermission(['cuti.pengajuan.cancel-own']);
    $pengajuan = buatPengajuanUntukTest('DRAFT', $pemilik);

    app(WorkflowService::class)->cancelDraft($pengajuan->id, $pemilik, 'batal jadi cuti');

    expect($pengajuan->fresh()->state->name())->toBe('DIBATALKAN');
});

test('cancelDraft: admin cancel-any bisa membatalkan pengajuan orang lain', function () {
    $pemilik = Pegawai::factory()->create();
    $admin = Pegawai::factory()->admin()->create();

    $pengajuan = buatPengajuanUntukTest('DRAFT', $pemilik);

    app(WorkflowService::class)->cancelDraft($pengajuan->id, $admin, 'admin batal');

    expect($pengajuan->fresh()->state->name())->toBe('DIBATALKAN');
});

// =============================================================================
// FIX 3b: cancelAfterApproved — pemilik dengan cancel-own bisa mencabut cuti sendiri
// =============================================================================

test('cancelAfterApproved: pemilik dengan cancel-own bisa mencabut cuti yang sudah disetujui', function () {
    $pemilik = buatPegawaiDenganPermission(['cuti.pengajuan.cancel-own']);

    app(SaldoLedgerService::class)->kreditAlokasi($pemilik->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanUntukTest('DISETUJUI', $pemilik, [
        'approved_at' => now(),
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
        'jumlah_hari_kerja' => 5,
    ]);

    // Simulasi debit_confirmed
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -5,
        'pengajuan_id' => $pengajuan->id,
        'aktor_pegawai_nip' => $pemilik->nip,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $pemilik, 'perlu dicabut');

    expect($pengajuan->fresh()->state->name())->toBe('DICABUT_SETELAH_DISETUJUI');
});

test('cancelAfterApproved: user cancel-own tidak bisa mencabut cuti orang lain', function () {
    $pemilik = Pegawai::factory()->create();
    $orangLain = buatPegawaiDenganPermission(['cuti.pengajuan.cancel-own']);

    $pengajuan = buatPengajuanUntukTest('DISETUJUI', $pemilik, [
        'approved_at' => now(),
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $orangLain, 'coba cabut');
})->throws(AuthorizationException::class);

test('cancelAfterApproved: user tanpa permission cancel-own dan cancel-any ditolak', function () {
    $pemilik = buatPegawaiDenganPermission(['cuti.pengajuan.view-own']);

    $pengajuan = buatPengajuanUntukTest('DISETUJUI', $pemilik, [
        'approved_at' => now(),
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $pemilik, 'coba cabut');
})->throws(AuthorizationException::class);
