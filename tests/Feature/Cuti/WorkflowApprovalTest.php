<?php

use App\Exceptions\Cuti\CancelTidakDiizinkanException;
use App\Exceptions\Cuti\TransitionTidakValidException;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Cuti\WorkflowService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;

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
 * Buat pengajuan CT dalam state tertentu untuk testing workflow.
 */
function buatPengajuanCT(string $state, ?Pegawai $pegawai = null, array $extra = []): CutiPengajuan
{
    $pegawai ??= Pegawai::factory()->create();

    return CutiPengajuan::factory()->create(array_merge([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => $state,
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $pegawai->nip,
        'atasan_langsung_current_nip' => $pegawai->nip,
        'pejabat_berwenang_current_nip' => $pegawai->nip,
    ], $extra));
}

// === Task 7.1: verify() ===

test('verify transitions ke DIVERIFIKASI', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon);

    app(WorkflowService::class)->verify($pengajuan->id, $petugas, 'OK');

    expect($pengajuan->fresh()->state->name())->toBe('DIVERIFIKASI');

    $this->assertDatabaseHas('cuti_pengajuan_approval_steps', [
        'pengajuan_id' => $pengajuan->id,
        'role' => 'petugas_kepegawaian',
        'action' => 'verify',
    ]);

    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $pengajuan->id,
        'state_from' => 'DIAJUKAN',
        'state_to' => 'DIVERIFIKASI',
    ]);
});

test('verify tanpa permission throws AuthorizationException', function () {
    $viewer = Pegawai::factory()->viewer()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN');

    app(WorkflowService::class)->verify($pengajuan->id, $viewer, 'coba');
})->throws(AuthorizationException::class);

// === Task 7.9: Idempotency ===

test('verify dua kali throws TransitionTidakValidException', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN');

    app(WorkflowService::class)->verify($pengajuan->id, $petugas);

    // Panggil lagi — state sudah DIVERIFIKASI
    app(WorkflowService::class)->verify($pengajuan->id, $petugas);
})->throws(TransitionTidakValidException::class);

// === Task 7.2: approveAtasan() ===

test('approveAtasan transitions ke DISETUJUI_ATASAN', function () {
    $atasan = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIVERIFIKASI', $pemohon, [
        'atasan_langsung_current_nip' => $atasan->nip,
    ]);

    app(WorkflowService::class)->approveAtasan($pengajuan->id, $atasan, 'setuju');

    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI_ATASAN');

    $this->assertDatabaseHas('cuti_pengajuan_approval_steps', [
        'pengajuan_id' => $pengajuan->id,
        'role' => 'atasan_langsung',
        'action' => 'approve',
    ]);
});

test('approveAtasan oleh bukan atasan throws AuthorizationException', function () {
    $bukAtasan = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIVERIFIKASI', $pemohon, [
        'atasan_langsung_current_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->approveAtasan($pengajuan->id, $bukAtasan, 'coba');
})->throws(AuthorizationException::class);

// === Task 7.3: approvePejabat() ===

test('approvePejabat transitions ke DISETUJUI dan commit ledger CT', function () {
    $pejabat = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    // Setup saldo dan debit_pending
    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanCT('DISETUJUI_ATASAN', $pemohon, [
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 5,
    ]);

    // Simulasi debit_pending yang sudah ada dari submit
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_pending',
        'jumlah_hari' => -5,
        'pengajuan_id' => $pengajuan->id,
        'aktor_pegawai_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat, 'final approval');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DISETUJUI')
        ->and($fresh->approved_at)->not->toBeNull();

    // Verifikasi ledger: debit_void + debit_confirmed harus ada
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'debit_void',
    ]);
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'debit_confirmed',
    ]);
});

// === Task 7.4: rejectByRole() ===

test('rejectByRole kepegawaian transitions ke DITOLAK_KEPEGAWAIAN', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN');

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $petugas, 'petugas_kepegawaian', 'data tidak lengkap');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DITOLAK_KEPEGAWAIAN')
        ->and($fresh->rejected_at)->not->toBeNull()
        ->and($fresh->rejection_reason)->toBe('data tidak lengkap');
});

test('rejectByRole atasan transitions ke DITOLAK_ATASAN', function () {
    $atasan = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DIVERIFIKASI');

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $atasan, 'atasan_langsung', 'tidak bisa ditinggal');

    expect($pengajuan->fresh()->state->name())->toBe('DITOLAK_ATASAN');
});

test('rejectByRole pejabat transitions ke DITOLAK_PEJABAT', function () {
    $pejabat = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DISETUJUI_ATASAN');

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $pejabat, 'pejabat_berwenang', 'ditolak pejabat');

    expect($pengajuan->fresh()->state->name())->toBe('DITOLAK_PEJABAT');
});

test('rejectByRole CT void pending ledger', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon);

    // Simulasi debit_pending
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_pending',
        'jumlah_hari' => -3,
        'pengajuan_id' => $pengajuan->id,
        'aktor_pegawai_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $petugas, 'petugas_kepegawaian', 'salah data');

    // Verifikasi void ledger entry ada
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'debit_void',
    ]);
});

// === Task 7.5: cancelDraft() ===

test('cancelDraft transitions DRAFT ke DIBATALKAN', function () {
    $pemohon = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DRAFT', $pemohon);

    app(WorkflowService::class)->cancelDraft($pengajuan->id, $pemohon, 'batal jadi cuti');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DIBATALKAN')
        ->and($fresh->cancelled_at)->not->toBeNull();
});

test('cancelDraft dari state DIAJUKAN throws TransitionTidakValidException', function () {
    $pemohon = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon);

    app(WorkflowService::class)->cancelDraft($pengajuan->id, $pemohon);
})->throws(TransitionTidakValidException::class);

// === Task 7.6: cancelAfterApproved() ===

test('cancelAfterApproved CT transitions ke DICABUT_SETELAH_DISETUJUI dengan refund', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanCT('DISETUJUI', $pemohon, [
        'approved_at' => now(),
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
        'jumlah_hari_kerja' => 5,
    ]);

    // Simulasi debit_confirmed yang sudah ada
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -5,
        'pengajuan_id' => $pengajuan->id,
        'aktor_pegawai_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $admin, 'perlu dicabut');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DICABUT_SETELAH_DISETUJUI')
        ->and($fresh->cancelled_at)->not->toBeNull();

    // Verifikasi refund ledger ada
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'kredit_refund',
    ]);
});

test('cancelAfterApproved CS throws CancelTidakDiizinkanException', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CS_TIER1',
        'state' => 'DISETUJUI',
        'approved_at' => now(),
        'submitted_at' => now()->subDays(3),
        'petugas_kepegawaian_current_nip' => $pemohon->nip,
        'atasan_langsung_current_nip' => $pemohon->nip,
        'pejabat_berwenang_current_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $admin, 'coba cabut CS');
})->throws(CancelTidakDiizinkanException::class);

// === Task 7.7: reassignApprover() ===

test('reassignApprover mengubah approver dan mencatat history', function () {
    $admin = Pegawai::factory()->admin()->create();
    $approverBaru = Pegawai::factory()->create();
    $pemohon = Pegawai::factory()->create();

    $pengajuan = buatPengajuanCT('DIVERIFIKASI', $pemohon, [
        'atasan_langsung_current_nip' => $pemohon->nip,
    ]);

    app(WorkflowService::class)->reassignApprover(
        $pengajuan->id,
        'atasan_langsung',
        $approverBaru->nip,
        $admin,
        'rotasi jabatan',
    );

    expect($pengajuan->fresh()->atasan_langsung_current_nip)->toBe($approverBaru->nip);

    $this->assertDatabaseHas('cuti_pengajuan_approver_history', [
        'pengajuan_id' => $pengajuan->id,
        'role' => 'atasan_langsung',
        'from_pegawai_nip' => $pemohon->nip,
        'to_pegawai_nip' => $approverBaru->nip,
        'aktor_pegawai_nip' => $admin->nip,
    ]);
});

// === Full flow test ===

test('full approval flow: DIAJUKAN → DIVERIFIKASI → DISETUJUI_ATASAN → DISETUJUI', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $atasan = Pegawai::factory()->admin()->create();
    $pejabat = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon, [
        'atasan_langsung_current_nip' => $atasan->nip,
        'pejabat_berwenang_current_nip' => $pejabat->nip,
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 5,
    ]);

    // Simulasi debit_pending
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_pending',
        'jumlah_hari' => -5,
        'pengajuan_id' => $pengajuan->id,
        'aktor_pegawai_nip' => $pemohon->nip,
    ]);

    $svc = app(WorkflowService::class);

    // Step 1: Verifikasi
    $svc->verify($pengajuan->id, $petugas, 'lengkap');
    expect($pengajuan->fresh()->state->name())->toBe('DIVERIFIKASI');

    // Step 2: Approve atasan
    $svc->approveAtasan($pengajuan->id, $atasan, 'disetujui');
    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI_ATASAN');

    // Step 3: Approve pejabat (final)
    $svc->approvePejabat($pengajuan->id, $pejabat, 'final');
    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DISETUJUI')
        ->and($fresh->approved_at)->not->toBeNull();

    // Verifikasi 3 approval steps tercatat
    expect($fresh->approvalSteps()->count())->toBe(3);

    // Verifikasi 3 state history tercatat
    expect($fresh->stateHistory()->count())->toBe(3);
});
