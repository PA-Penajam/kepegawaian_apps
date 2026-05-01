<?php

use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use App\Services\Cuti\CarryOverProcessorService;
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

test('carryOver expire bucket N-2 ke saldo 0', function () {
    $p = Pegawai::factory()->create();
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2024, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2024, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 8, 'aktor_pegawai_nip' => $p->nip]);

    $this->travelTo('2026-01-01');
    app(CarryOverProcessorService::class)->process($p);

    expect(app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2024))->toBe(0);
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pegawai_nip' => $p->nip, 'tahun_hak' => 2024,
        'jenis_transaksi' => 'expire', 'jumlah_hari' => -8,
    ]);
});

test('carryOver cap N-1 max 6 hari', function () {
    $p = Pegawai::factory()->create();
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 10, 'aktor_pegawai_nip' => $p->nip]);

    $this->travelTo('2026-01-01');
    app(CarryOverProcessorService::class)->process($p);

    // 10 hari - cap ke 6 = expire 4
    expect(app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2025))->toBe(6);
});

test('carryOver kredit hak tahun N = 12', function () {
    $p = Pegawai::factory()->create();

    $this->travelTo('2026-01-01');
    app(CarryOverProcessorService::class)->process($p);

    expect(app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2026))->toBe(12);
    $this->assertDatabaseHas('cuti_alokasi_tahunan', ['pegawai_nip' => $p->nip, 'tahun_hak' => 2026, 'hak_awal' => 12]);
});

test('carryOver idempotent - 2 run tidak duplicate', function () {
    $p = Pegawai::factory()->create();
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2024, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2024, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 5, 'aktor_pegawai_nip' => $p->nip]);
    CutiAlokasiTahunan::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'hak_awal' => 12]);
    CutiSaldoLedger::create(['pegawai_nip' => $p->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => 2025, 'jenis_transaksi' => 'kredit', 'jumlah_hari' => 10, 'aktor_pegawai_nip' => $p->nip]);

    $this->travelTo('2026-01-01');
    $svc = app(CarryOverProcessorService::class);
    $svc->process($p);
    $svc->process($p); // run lagi

    // Kredit N hanya 1 row, expire N-2 hanya 1 row
    $kreditN = CutiSaldoLedger::where('pegawai_nip', $p->nip)->where('tahun_hak', 2026)->where('jenis_transaksi', 'kredit')->count();
    $expireN2 = CutiSaldoLedger::where('pegawai_nip', $p->nip)->where('tahun_hak', 2024)->where('jenis_transaksi', 'expire')->count();

    expect($kreditN)->toBe(1)->and($expireN2)->toBe(1);
});

test('command cuti:carry-over memproses pegawai aktif', function () {
    $p = Pegawai::factory()->create();
    $this->travelTo('2026-01-01');

    $this->artisan('cuti:carry-over', ['--nip' => $p->nip])
        ->assertSuccessful();

    expect(app(SaldoLedgerService::class)->saldoBucket($p->nip, 'CT', 2026))->toBe(12);
});
