<?php

use App\Exceptions\Cuti\CancelTidakDiizinkanException;
use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Exceptions\Cuti\TransitionTidakValidException;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Cuti\WorkflowService;
use Carbon\Carbon;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
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
    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon, [
        'petugas_kepegawaian_current_nip' => $petugas->nip,
    ]);

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
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon, [
        'petugas_kepegawaian_current_nip' => $petugas->nip,
    ]);

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
        'pejabat_berwenang_current_nip' => $pejabat->nip,
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
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon, [
        'petugas_kepegawaian_current_nip' => $petugas->nip,
    ]);

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $petugas, 'petugas_kepegawaian', 'data tidak lengkap');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DITOLAK_KEPEGAWAIAN')
        ->and($fresh->rejected_at)->not->toBeNull()
        ->and($fresh->rejection_reason)->toBe('data tidak lengkap');
});

test('rejectByRole atasan transitions ke DITOLAK_ATASAN', function () {
    $atasan = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DIVERIFIKASI', $pemohon, [
        'atasan_langsung_current_nip' => $atasan->nip,
    ]);

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $atasan, 'atasan_langsung', 'tidak bisa ditinggal');

    expect($pengajuan->fresh()->state->name())->toBe('DITOLAK_ATASAN');
});

test('rejectByRole pejabat transitions ke DITOLAK_PEJABAT', function () {
    $pejabat = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $pengajuan = buatPengajuanCT('DISETUJUI_ATASAN', $pemohon, [
        'pejabat_berwenang_current_nip' => $pejabat->nip,
    ]);

    app(WorkflowService::class)->rejectByRole($pengajuan->id, $pejabat, 'pejabat_berwenang', 'ditolak pejabat');

    expect($pengajuan->fresh()->state->name())->toBe('DITOLAK_PEJABAT');
});

test('rejectByRole CT void pending ledger', function () {
    $this->travelTo('2026-06-01');
    $petugas = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    $pengajuan = buatPengajuanCT('DIAJUKAN', $pemohon, [
        'petugas_kepegawaian_current_nip' => $petugas->nip,
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-08',
        'jumlah_hari_kerja' => 3,
    ]);

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
    $this->travelTo('2026-06-01');
    $admin = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($pemohon->nip, 'CT', 2026, 12, 'init');

    // Cuti belum berjalan (mulai di masa depan) sehingga refund penuh sesuai
    // jumlah_hari_kerja, konsisten dengan logika hitungRefund() pada state ini.
    $pengajuan = buatPengajuanCT('DISETUJUI', $pemohon, [
        'approved_at' => now(),
        'tanggal_mulai' => now()->addWeek()->toDateString(),
        'tanggal_selesai' => now()->addWeek()->addDays(4)->toDateString(),
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
        'petugas_kepegawaian_current_nip' => $petugas->nip,
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

test('anti-self-approval dan salah current_nip ditolak AuthorizationException di WorkflowService', function () {
    $pemohon = Pegawai::factory()->admin()->create();
    $orangLain = Pegawai::factory()->admin()->create();
    $aktorSah = Pegawai::factory()->admin()->create();
    $svc = app(WorkflowService::class);

    // Self-approval verify meskipun current_nip sama dengan pemohon
    $p1 = buatPengajuanCT('DIAJUKAN', $pemohon, ['petugas_kepegawaian_current_nip' => $pemohon->nip]);
    expect(fn () => $svc->verify($p1->id, $pemohon))->toThrow(AuthorizationException::class);

    // Salah current_nip verify
    $p1b = buatPengajuanCT('DIAJUKAN', $pemohon, ['petugas_kepegawaian_current_nip' => $aktorSah->nip]);
    expect(fn () => $svc->verify($p1b->id, $orangLain))->toThrow(AuthorizationException::class);

    // Self-approval & salah current_nip approvePejabat
    $p3 = buatPengajuanCT('DISETUJUI_ATASAN', $pemohon, ['pejabat_berwenang_current_nip' => $pemohon->nip]);
    expect(fn () => $svc->approvePejabat($p3->id, $pemohon))->toThrow(AuthorizationException::class);

    $p3b = buatPengajuanCT('DISETUJUI_ATASAN', $pemohon, ['pejabat_berwenang_current_nip' => $aktorSah->nip]);
    expect(fn () => $svc->approvePejabat($p3b->id, $orangLain))->toThrow(AuthorizationException::class);
});

test('cancelAfterApproved menolak cuti yang sudah berlalu dengan CancelTidakDiizinkanException', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();

    $pengajuan = buatPengajuanCT('DISETUJUI', $pemohon, [
        'approved_at' => now()->subMonths(2),
        'tanggal_mulai' => now()->subDays(10)->toDateString(),
        'tanggal_selesai' => now()->subDays(5)->toDateString(),
        'jumlah_hari_kerja' => 4,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($pengajuan->id, $admin, 'coba cabut cuti lampau');
})->throws(CancelTidakDiizinkanException::class);

test('penolakan pengajuan CT pending lintas tahun tidak membangkitkan saldo hangus N-2 dan tetap menjaga cap N-1 maksimal 6 hari', function () {
    $pemohon = Pegawai::factory()->create(['tmt_cpns' => '2020-01-01']);
    $petugas = Pegawai::factory()->admin()->create();
    $saldoSvc = app(SaldoLedgerService::class);

    $this->travelTo('2025-12-01 09:00:00');
    $saldoSvc->kreditAlokasi($pemohon->nip, 'CT', 2024, 5, 'Sisa 2024');
    $saldoSvc->kreditAlokasi($pemohon->nip, 'CT', 2025, 10, 'Hak 2025');

    // Submit CT 10 hari kerja pada Desember 2025 -> debit_pending 5 dari 2024 + 5 dari 2025
    $pengajuan = app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2025-12-08',
        'tanggal_selesai' => '2025-12-19',
        'alasan' => 'Cuti akhir tahun 2025',
    ]);
    $pengajuan->forceFill(['petugas_kepegawaian_current_nip' => $petugas->nip])->save();

    expect($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2024))->toBe(0)
        ->and($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025))->toBe(5);

    // Awal 2026: carry-over berjalan
    $this->travelTo('2026-01-01 01:00:00');
    $this->artisan('cuti:carry-over', ['--nip' => $pemohon->nip])->assertSuccessful();

    // 2026-01-02: pengajuan 2025 ditolak oleh petugas kepegawaian
    $this->travelTo('2026-01-02 09:00:00');
    app(WorkflowService::class)->rejectByRole($pengajuan->id, $petugas, 'petugas_kepegawaian', 'Ditolak lintas tahun');

    // Saldo 2024 (N-2) wajib tetap 0 dan saldo 2025 (N-1) wajib <= 6 (tepat 6)
    expect($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2024))->toBe(0)
        ->and($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025))->toBeLessThanOrEqual(6)
        ->and($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025))->toBe(6)
        ->and($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2026))->toBe(12);

    // Pastikan pengajuan 2026 untuk 19 hari kerja ditolak karena total saldo aktif maksimal 18 (6 + 12)
    expect(fn () => app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-02-26',
        'alasan' => 'Uji batas saldo pasca void lintas tahun',
    ]))->toThrow(SaldoTidakCukupException::class);

    Carbon::setTestNow();
});

test('penolakan pengajuan CT pending N-1 setelah pengajuan tahun N memakai bucket N-1 tetap menegakkan cap carry-over maksimal 6 hari', function () {
    $pemohon = Pegawai::factory()->create(['tmt_cpns' => '2020-01-01']);
    $petugas = Pegawai::factory()->admin()->create();
    $atasan = Pegawai::factory()->admin()->create();
    $pejabat = Pegawai::factory()->admin()->create();
    $saldoSvc = app(SaldoLedgerService::class);
    $pengajuanSvc = app(PengajuanCutiService::class);
    $workflowSvc = app(WorkflowService::class);

    // 2025-12-20: alokasi 2025 = 10 hari, submit CT B (6 hari kerja) -> sisa 2025 = 4
    $this->travelTo('2025-12-20 09:00:00');
    $saldoSvc->kreditAlokasi($pemohon->nip, 'CT', 2025, 10, 'Hak 2025');

    $pengajuanB = $pengajuanSvc->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2025-12-24',
        'tanggal_selesai' => '2025-12-31',
        'alasan' => 'Cuti akhir tahun 2025 B',
    ]);
    $pengajuanB->forceFill(['petugas_kepegawaian_current_nip' => $petugas->nip])->save();

    expect($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025))->toBe(4);

    // 2026-01-01: carry-over berjalan (bucket 2025 = 4 <= 6, kredit 2026 = 12)
    $this->travelTo('2026-01-01 01:00:00');
    $this->artisan('cuti:carry-over', ['--nip' => $pemohon->nip])->assertSuccessful();

    // 2026-01-02: submit & setujui CT C (4 hari kerja di 2026-02) -> memakan 4 hari dari bucket 2025
    $this->travelTo('2026-01-02 09:00:00');
    $pengajuanC = $pengajuanSvc->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-02-05',
        'alasan' => 'Cuti Februari 2026 C',
    ]);
    $pengajuanC->forceFill([
        'petugas_kepegawaian_current_nip' => $petugas->nip,
        'atasan_langsung_current_nip' => $atasan->nip,
        'pejabat_berwenang_current_nip' => $pejabat->nip,
    ])->save();

    $workflowSvc->verify($pengajuanC->id, $petugas);
    $workflowSvc->approveAtasan($pengajuanC->id, $atasan);
    $workflowSvc->approvePejabat($pengajuanC->id, $pejabat);

    expect($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025))->toBe(0);

    // 2026-01-04: CT B dari 2025 ditolak -> bucket 2025 maksimal tersisa 2 (4 dipakai C + 2 sisa = 6)
    $this->travelTo('2026-01-04 09:00:00');
    $workflowSvc->rejectByRole($pengajuanB->id, $petugas, 'petugas_kepegawaian', 'Ditolak di tahun baru');

    $sisaBucket2025 = $saldoSvc->saldoBucket($pemohon->nip, 'CT', 2025);
    $confirmed2025Di2026 = (int) CutiSaldoLedger::query()
        ->where('pegawai_nip', $pemohon->nip)
        ->where('jenis_cuti_kode', 'CT')
        ->where('tahun_hak', 2025)
        ->where('jenis_transaksi', 'debit_confirmed')
        ->where('pengajuan_id', $pengajuanC->id)
        ->sum('jumlah_hari');

    expect($sisaBucket2025)->toBe(2)
        ->and(abs($confirmed2025Di2026) + $sisaBucket2025)->toBeLessThanOrEqual(6)
        ->and($saldoSvc->saldoBucket($pemohon->nip, 'CT', 2026))->toBe(12);

    Carbon::setTestNow();
});
