<?php

use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Buat jenis cuti 'CT' sebagai dependency FK
    CutiJenisMaster::firstOrCreate(
        ['kode' => 'CT'],
        ['nama' => 'Cuti Tahunan', 'saldo_driven' => true, 'aktif' => true]
    );
});

test('saldoBucket mengembalikan sum jumlah_hari dari ledger', function () {
    $pegawai = Pegawai::factory()->create();

    CutiSaldoLedger::create([
        'pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026,
        'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $pegawai->nip,
    ]);
    CutiSaldoLedger::create([
        'pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_confirmed', 'jumlah_hari' => -3, 'aktor_pegawai_nip' => $pegawai->nip,
    ]);

    $svc = app(SaldoLedgerService::class);
    expect($svc->saldoBucket($pegawai->nip, 'CT', 2026))->toBe(9);
});

test('bucketsAktif mengembalikan bucket dengan saldo positif terurut ASC', function () {
    $p = Pegawai::factory()->create();

    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 4, 'aktor_pegawai_nip' => $p->nip]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $p->nip]);

    $svc = app(SaldoLedgerService::class);
    $buckets = $svc->bucketsAktif($p->nip, 'CT');
    expect($buckets->pluck('tahun_hak')->all())->toBe([2025, 2026]);
});

test('debitPendingFifo single bucket saldo cukup', function () {
    $p = Pegawai::factory()->create();

    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $p->nip]);
    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'jumlah_hari_kerja' => 5,
    ]);

    $svc = app(SaldoLedgerService::class);
    $rows = $svc->debitPendingFifo($pengajuan);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->jumlah_hari)->toBe(-5)
        ->and($rows[0]->tahun_hak)->toBe(2026)
        ->and($svc->saldoBucket($p->nip, 'CT', 2026))->toBe(7);
});

test('debitPendingFifo split lintas bucket FIFO', function () {
    $p = Pegawai::factory()->create();

    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 4, 'aktor_pegawai_nip' => $p->nip]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 12, 'aktor_pegawai_nip' => $p->nip]);
    $pengajuan = CutiPengajuan::factory()->create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'jumlah_hari_kerja' => 7]);

    $rows = app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->tahun_hak)->toBe(2025)
        ->and($rows[0]->jumlah_hari)->toBe(-4)
        ->and($rows[1]->tahun_hak)->toBe(2026)
        ->and($rows[1]->jumlah_hari)->toBe(-3);
});

test('debitPendingFifo saldo tidak cukup throws SaldoTidakCukupException', function () {
    $p = Pegawai::factory()->create();

    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 5, 'aktor_pegawai_nip' => $p->nip]);
    $pengajuan = CutiPengajuan::factory()->create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'jumlah_hari_kerja' => 10]);

    app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);
})->throws(SaldoTidakCukupException::class);

test('commitConfirmed menulis void dan confirmed per bucket', function () {
    $p = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'jumlah_hari_kerja' => 7]);

    // Simulasi 2 row debit_pending dari 2 bucket
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -4, 'aktor_pegawai_nip' => $p->nip]);
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -3, 'aktor_pegawai_nip' => $p->nip]);

    app(SaldoLedgerService::class)->commitConfirmed($pengajuan);

    $void = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_void')->get();
    $confirmed = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_confirmed')->get();

    expect($void)->toHaveCount(2)
        ->and($confirmed)->toHaveCount(2)
        ->and(abs($confirmed->sum('jumlah_hari')))->toBe(7);
});

test('voidPending menulis void per bucket tanpa confirmed', function () {
    $p = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT']);

    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -4, 'aktor_pegawai_nip' => $p->nip]);
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'debit_pending', 'jumlah_hari' => -3, 'aktor_pegawai_nip' => $p->nip]);

    app(SaldoLedgerService::class)->voidPending($pengajuan);

    $void = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_void')->get();
    $confirmed = CutiSaldoLedger::where('pengajuan_id', $pengajuan->id)->where('jenis_transaksi', 'debit_confirmed')->get();

    expect($void)->toHaveCount(2)
        ->and($confirmed)->toHaveCount(0);
});

test('processRefund partial FIFO setelah cuti berjalan', function () {
    $p = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $p->nip,
        'jenis_cuti_kode' => 'CT',
        'jumlah_hari_kerja' => 5,
        'tanggal_mulai' => now()->subDays(1)->toDateString(),
        'tanggal_selesai' => now()->addDays(5)->toDateString(),
    ]);
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'debit_confirmed', 'jumlah_hari' => -2, 'aktor_pegawai_nip' => $p->nip]);
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'debit_confirmed', 'jumlah_hari' => -3, 'aktor_pegawai_nip' => $p->nip]);

    $refundRows = app(SaldoLedgerService::class)->processRefund($pengajuan);

    // Refund harus ada untuk sisa hari kerja setelah hari ini
    expect($refundRows)->not->toBeEmpty();
    // Semua refund row bertipe kredit_refund dan positif
    foreach ($refundRows as $row) {
        expect($row->jenis_transaksi)->toBe('kredit_refund')
            ->and($row->jumlah_hari)->toBeGreaterThan(0);
    }
});

test('processRefund full ketika belum mulai cuti', function () {
    $p = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'jumlah_hari_kerja' => 5,
        'tanggal_mulai' => now()->addWeek()->toDateString(),
        'tanggal_selesai' => now()->addWeeks(2)->toDateString(),
    ]);
    CutiSaldoLedger::create(['pengajuan_id' => $pengajuan->id, 'pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2026, 'jenis_transaksi' => 'debit_confirmed', 'jumlah_hari' => -5, 'aktor_pegawai_nip' => $p->nip]);

    $refundRows = app(SaldoLedgerService::class)->processRefund($pengajuan);

    expect($refundRows)->toHaveCount(1)
        ->and($refundRows[0]->jumlah_hari)->toBe(5);
});

test('kreditAlokasi membuat anchor dan kredit ledger', function () {
    $p = Pegawai::factory()->create();

    app(SaldoLedgerService::class)->kreditAlokasi($p->nip, 'CT', 2026, 12, 'Inisialisasi awal tahun');

    expect(app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2026))->toBe(12);
    $this->assertDatabaseHas('cuti_alokasi_tahunan', ['pegawai_nip' => $p->nip, 'tahun_hak' => 2026, 'hak_awal' => 12]);
});

test('kreditAlokasi idempotent - 2 panggilan hanya 1 row kredit', function () {
    $p = Pegawai::factory()->create();
    $svc = app(SaldoLedgerService::class);

    $svc->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init');
    $svc->kreditAlokasi($p->nip, 'CT', 2026, 12, 'init'); // panggil lagi

    $count = CutiSaldoLedger::where('pegawai_nip', $p->nip)->where('jenis_transaksi', 'kredit')->count();
    expect($count)->toBe(1);
});
