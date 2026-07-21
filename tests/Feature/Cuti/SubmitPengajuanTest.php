<?php

use App\Exceptions\Cuti\AlokasiTidakAdaException;
use App\Exceptions\Cuti\CrossYearLeaveException;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Exceptions\Cuti\SubmitTerlambatException;
use App\Models\Pegawai;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\Cuti\SaldoLedgerService;
use Database\Seeders\CutiJenisMasterSeeder;

beforeEach(function () {
    $this->travelTo('2026-01-01');
    $this->seed(CutiJenisMasterSeeder::class);
});

// === Task 6.1: Cross-year reject ===

test('submit cross-year ditolak dengan CrossYearLeaveException', function () {
    $p = Pegawai::factory()->create();

    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-12-28',
        'tanggal_selesai' => '2027-01-05',
        'alasan' => 'libur akhir tahun',
    ]);
})->throws(CrossYearLeaveException::class);

// === Task 6.2: Alokasi belum ada → reject ===

test('submit tanpa alokasi ditolak dengan AlokasiTidakAdaException', function () {
    $p = Pegawai::factory()->create();

    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-06-01',
        'tanggal_selesai' => '2026-06-05',
        'alasan' => 'liburan',
    ]);
})->throws(AlokasiTidakAdaException::class);

// === Task 6.3: Happy path submit CT ===

test('submit CT happy path - state DIAJUKAN dan debit_pending tertulis', function () {
    $p = Pegawai::factory()->create();

    // Setup alokasi + kredit saldo
    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');

    $pengajuan = app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-10',
        'alasan' => 'liburan keluarga',
    ]);

    expect($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($pengajuan->nomor_pengajuan)->not->toBeEmpty()
        ->and($pengajuan->jumlah_hari_kerja)->toBeGreaterThan(0);

    // Verifikasi debit_pending ada di ledger
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'debit_pending',
    ]);
});

// === Task 6.4: generateNomor ===

test('generateNomor menghasilkan format CUTI/YYYY/NIP-pendek/counter', function () {
    $p = Pegawai::factory()->create();
    $svc = app(PengajuanCutiService::class);

    $n1 = $svc->generateNomor(2026, $p->nip);
    expect($n1)->toMatch('/^CUTI\/2026\/\d+\/\d{4}$/');

    // Buat satu pengajuan agar counter naik
    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');
    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-08-03',
        'tanggal_selesai' => '2026-08-07',
        'alasan' => 'cuti pertama',
    ]);

    $n2 = $svc->generateNomor(2026, $p->nip);
    expect($n1)->not->toBe($n2);
});

// === Task 6.5: CT rule tolak jika submit kurang dari H-3 ===

test('CT rule tolak jika submit kurang dari H-3', function () {
    $p = Pegawai::factory()->create();
    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', now()->year, 12, 'init');

    // tanggal_mulai besok (kurang dari H-3)
    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => now()->addDay()->toDateString(),
        'tanggal_selesai' => now()->addDays(3)->toDateString(),
        'alasan' => 'mendadak',
    ]);
})->throws(SubmitTerlambatException::class);

// === Task 6.7: Overlap detection ===

test('submit dengan tanggal overlap ditolak', function () {
    $p = Pegawai::factory()->create();
    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');

    // Submit pertama
    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-10',
        'alasan' => 'liburan 1',
    ]);

    // Submit kedua dengan tanggal overlap
    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-07-08',
        'tanggal_selesai' => '2026-07-15',
        'alasan' => 'liburan 2',
    ]);
})->throws(OverlapPengajuanException::class);
