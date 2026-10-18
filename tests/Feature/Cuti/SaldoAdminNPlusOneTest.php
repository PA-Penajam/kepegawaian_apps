<?php

use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
    $this->withoutVite();
});

function buatLimaAlokasiDenganSaldo(): void
{
    foreach (range(1, 5) as $i) {
        $pegawai = Pegawai::factory()->create();

        CutiAlokasiTahunan::create([
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => 2026,
            'hak_awal' => 12,
        ]);
        CutiSaldoLedger::create([
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => 2026,
            'jenis_transaksi' => 'kredit',
            'jumlah_hari' => 12,
            'aktor_pegawai_nip' => $pegawai->nip,
        ]);
    }
}

test('adminIndex menghitung saldo tanpa query per baris (N+1)', function () {
    $admin = Pegawai::factory()->admin()->create();
    buatLimaAlokasiDenganSaldo();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->actingAs($admin)
        ->get(route('admin.cuti.saldo.index', ['tahun' => 2026]))
        ->assertSuccessful();

    $ledgerQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q) => str_contains($q['query'], 'cuti_saldo_ledger'));

    // 5 baris alokasi harus dihitung dengan query agregat, bukan 5x SUM per baris
    expect($ledgerQueries->count())->toBeLessThanOrEqual(2);
});

test('adminIndex menampilkan saldo yang benar per baris', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();

    CutiAlokasiTahunan::create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'hak_awal' => 12,
    ]);
    CutiSaldoLedger::create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'kredit',
        'jumlah_hari' => 12,
        'aktor_pegawai_nip' => $pegawai->nip,
    ]);
    CutiSaldoLedger::create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -3,
        'aktor_pegawai_nip' => $pegawai->nip,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.cuti.saldo.index', ['tahun' => 2026]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/saldo/admin-index')
            ->where('alokasiList.data', function ($data) use ($pegawai): bool {
                $item = collect($data)->firstWhere('pegawai_nip', $pegawai->nip);

                return $item !== null && (int) $item['saldo_saat_ini'] === 9;
            })
        );
});
