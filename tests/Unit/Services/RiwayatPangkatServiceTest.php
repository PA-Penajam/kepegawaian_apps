<?php

use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Services\RiwayatPangkatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeRiwayatPangkatData(RefPangkat $pangkat, array $overrides = []): array
{
    return array_merge([
        'ref_pangkat_id' => $pangkat->id,
        'no_sk' => 'SK-001/PA.PNJ/III/2026',
        'tanggal_sk' => '2026-03-01',
        'tmt' => '2026-04-01',
        'pejabat_penetap' => 'Ketua Pengadilan Agama Penajam',
        'masa_kerja_tahun' => 10,
        'masa_kerja_bulan' => 4,
        'gaji_pokok' => 5250000.50,
        'is_aktif' => false,
        'keterangan' => 'Riwayat pangkat hasil pengujian',
    ], $overrides);
}

it('store menyimpan riwayat aktif lalu menonaktifkan riwayat aktif lain', function () {
    $service = new RiwayatPangkatService;
    $oldPangkat = RefPangkat::factory()->create();
    $newPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $oldPangkat->id,
    ]);

    $oldRiwayat = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $oldPangkat->id,
        'is_aktif' => true,
    ]);

    $riwayatPangkat = $service->store($pegawai, makeRiwayatPangkatData($newPangkat, [
        'is_aktif' => '1',
    ]));

    expect($riwayatPangkat->is_aktif)->toBeTrue()
        ->and($oldRiwayat->refresh()->is_aktif)->toBeFalse()
        ->and($pegawai->refresh()->ref_pangkat_id)->toBe($newPangkat->id);
});

it('store memaksa is_aktif menjadi false saat nilai tidak dikirim', function () {
    $service = new RiwayatPangkatService;
    $pangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
    ]);

    $riwayatPangkat = $service->store($pegawai, makeRiwayatPangkatData($pangkat, [
        'is_aktif' => null,
    ]));

    expect($riwayatPangkat->refresh()->is_aktif)->toBeFalse()
        ->and($pegawai->refresh()->ref_pangkat_id)->toBe($pangkat->id);
});

it('update menyimpan data aktif lalu sinkronisasi status riwayat', function () {
    $service = new RiwayatPangkatService;
    $firstPangkat = RefPangkat::factory()->create();
    $secondPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $firstPangkat->id,
    ]);

    $oldActive = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $firstPangkat->id,
        'is_aktif' => true,
    ]);

    $toUpdate = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $secondPangkat->id,
        'is_aktif' => false,
    ]);

    $updated = $service->update($toUpdate, $pegawai, makeRiwayatPangkatData($secondPangkat, [
        'no_sk' => 'SK-UPDATED/PA.PNJ/III/2026',
        'is_aktif' => 'on',
    ]));

    expect($updated->no_sk)->toBe('SK-UPDATED/PA.PNJ/III/2026')
        ->and($updated->is_aktif)->toBeTrue()
        ->and($oldActive->refresh()->is_aktif)->toBeFalse()
        ->and($pegawai->refresh()->ref_pangkat_id)->toBe($secondPangkat->id);
});
