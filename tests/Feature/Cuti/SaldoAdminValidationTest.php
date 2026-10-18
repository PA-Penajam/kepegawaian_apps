<?php

use App\Models\Pegawai;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;

beforeEach(function () {
    $this->travelTo('2026-01-01');
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

function buatAdminSaldo(): Pegawai
{
    return Pegawai::factory()->admin()->create();
}

test('init saldo menolak NIP yang tidak terdaftar', function () {
    $admin = buatAdminSaldo();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => '000000000000000000',
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => 12,
        ])
        ->assertSessionHasErrors('pegawai_nip');
});

test('init saldo menolak jenis cuti yang tidak dikenal', function () {
    $admin = buatAdminSaldo();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'XX_TIDAK_ADA',
            'tahun' => 2026,
            'jumlah_hari' => 12,
        ])
        ->assertSessionHasErrors('jenis_cuti_kode');
});

test('init saldo menolak jumlah hari nol', function () {
    $admin = buatAdminSaldo();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => 0,
        ])
        ->assertSessionHasErrors('jumlah_hari');
});

test('adjust saldo menolak keterangan yang terlalu pendek', function () {
    $admin = buatAdminSaldo();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.adjust'), [
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => -2,
            'keterangan' => 'koreksi',
        ])
        ->assertSessionHasErrors('keterangan');
});

test('adjust saldo menolak jenis cuti yang tidak dikenal', function () {
    $admin = buatAdminSaldo();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.adjust'), [
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'XX_TIDAK_ADA',
            'tahun' => 2026,
            'jumlah_hari' => -2,
            'keterangan' => 'Koreksi saldo awal tahun 2026',
        ])
        ->assertSessionHasErrors('jenis_cuti_kode');
});

test('init saldo happy path menyimpan alokasi dan kredit ledger', function () {
    $admin = buatAdminSaldo();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => 12,
            'keterangan' => 'Hak awal 2026',
        ])
        ->assertRedirect(route('admin.cuti.saldo.index'));

    $this->assertDatabaseHas('cuti_alokasi_tahunan', [
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'hak_awal' => 12,
    ]);
    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'kredit',
        'jumlah_hari' => 12,
    ]);
});
