<?php

use App\Exceptions\Cuti\AlokasiTidakAdaException;
use App\Exceptions\Cuti\CrossYearLeaveException;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Exceptions\Cuti\SubmitTerlambatException;
use App\Models\Cuti\CutiPengajuanLampiran;
use App\Models\Pegawai;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\Cuti\SaldoLedgerService;
use Database\Seeders\CutiJenisMasterSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('submit CS_TIER2 menyimpan lampiran di dalam transaksi sebelum validasi rule', function () {
    Storage::fake('local');
    $p = Pegawai::factory()->create();
    $file = UploadedFile::fake()->create('surat_dokter.pdf', 120, 'application/pdf');

    $pengajuan = app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CS_TIER2',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-02-20',
        'alasan' => 'Rawat inap',
    ], [$file]);

    expect($pengajuan->state->name())->toBe('DIAJUKAN');
    $lampiran = CutiPengajuanLampiran::where('pengajuan_id', $pengajuan->id)->first();
    expect($lampiran)->not->toBeNull();
    Storage::disk('local')->assertExists($lampiran->path_file);
});

test('submit gagal rule menghapus file lampiran yang terlanjur disimpan', function () {
    Storage::fake('local');
    // Masa kerja kurang dari 5 tahun untuk CLTN
    $p = Pegawai::factory()->create(['tmt_cpns' => '2024-01-01']);
    $file = UploadedFile::fake()->create('lampiran.pdf', 100, 'application/pdf');

    expect(fn () => app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-03-02',
        'tanggal_selesai' => '2026-03-10',
        'alasan' => 'Alasan pribadi penting',
    ], [$file]))->toThrow(DomainException::class);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('submit akhir pekan murni dengan hari kerja 0 ditolak', function () {
    $p = Pegawai::factory()->create();

    app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CS_TIER1',
        'tanggal_mulai' => '2026-01-03', // Sabtu
        'tanggal_selesai' => '2026-01-04', // Minggu
        'alasan' => 'Sakit di akhir pekan',
    ]);
})->throws(DomainException::class, 'minimal 1 hari kerja');

test('submit CT dapat memakai akumulasi carry-over tahun sebelumnya dan debit FIFO', function () {
    $p = Pegawai::factory()->create();
    $saldoSvc = app(SaldoLedgerService::class);
    $saldoSvc->kreditAlokasi($p->nip, 'CT', 2025, 4, 'sisa 2025');
    $saldoSvc->kreditAlokasi($p->nip, 'CT', 2026, 2, 'hak 2026');

    $pengajuan = app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-07-06',
        'tanggal_selesai' => '2026-07-10',
        'alasan' => 'Pakai carry over dan hak tahun berjalan',
    ]);

    expect($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($saldoSvc->saldoBucket($p->nip, 'CT', 2025))->toBe(0)
        ->and($saldoSvc->saldoBucket($p->nip, 'CT', 2026))->toBe(1);
});

test('submit CS_TIER1 melebihi 14 hari kalender menampilkan pesan konstanta yang terinterpolasi', function () {
    $p = Pegawai::factory()->create();

    expect(fn () => app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CS_TIER1',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-02-20',
        'alasan' => 'Sakit lebih dari 14 hari',
    ]))->toThrow(DomainException::class, 'maksimal 14 hari kalender');
});

test('submit CT di tahun yang sama setelah CB aktif ditolak DomainException secara simetris', function () {
    $p = Pegawai::factory()->create(['tmt_cpns' => '2018-01-01']);
    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');

    $svc = app(PengajuanCutiService::class);
    $svc->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-06-05',
        'alasan' => 'Cuti besar tahun 2026',
    ]);

    expect(fn () => $svc->submit([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-11-02',
        'tanggal_selesai' => '2026-11-04',
        'alasan' => 'Mencoba cuti tahunan setelah cuti besar di tahun yang sama',
    ]))->toThrow(DomainException::class, 'cuti besar');
});
