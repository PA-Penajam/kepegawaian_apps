<?php

use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use App\Services\RiwayatJabatanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeRiwayatJabatanData(array $overrides = []): array
{
    $jabatan = RefJabatan::factory()->create();
    $unitKerja = RefUnitKerja::factory()->create();

    return array_merge([
        'ref_jabatan_id' => $jabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
        'no_sk' => fake()->unique()->numerify('SK-####'),
        'tanggal_sk' => '2025-01-15',
        'tmt' => '2025-02-01',
        'pejabat_penetap' => 'Ketua Pengadilan',
        'is_aktif' => true,
        'keterangan' => 'Riwayat jabatan aktif',
    ], $overrides);
}

test('store creates active riwayat jabatan and syncs pegawai data', function () {
    $pegawai = Pegawai::factory()->create();
    $riwayatAktifSebelumnya = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);
    $data = makeRiwayatJabatanData();
    $service = new RiwayatJabatanService;

    $riwayatJabatan = $service->store($pegawai, $data);

    expect($riwayatJabatan->exists)->toBeTrue();
    expect($riwayatJabatan->pegawai_id)->toBe($pegawai->id);
    expect($riwayatJabatan->is_aktif)->toBeTrue();
    expect($riwayatAktifSebelumnya->refresh()->is_aktif)->toBeFalse();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($data['ref_jabatan_id']);
    expect($pegawai->ref_unit_kerja_id)->toBe($data['ref_unit_kerja_id']);
});

test('update activates riwayat jabatan and syncs pegawai data', function () {
    $pegawai = Pegawai::factory()->create();
    $riwayatAktifSebelumnya = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => false,
    ]);
    $data = makeRiwayatJabatanData([
        'no_sk' => $riwayatJabatan->no_sk,
        'is_aktif' => true,
    ]);
    $service = new RiwayatJabatanService;

    $updatedRiwayatJabatan = $service->update($riwayatJabatan, $pegawai, $data);

    expect($updatedRiwayatJabatan->id)->toBe($riwayatJabatan->id);
    expect($updatedRiwayatJabatan->is_aktif)->toBeTrue();
    expect($riwayatAktifSebelumnya->refresh()->is_aktif)->toBeFalse();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($data['ref_jabatan_id']);
    expect($pegawai->ref_unit_kerja_id)->toBe($data['ref_unit_kerja_id']);
});

test('syncRiwayatAktif deactivates other records and updates pegawai references', function () {
    $pegawai = Pegawai::factory()->create();
    $riwayatAktifSebelumnya = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);
    $service = new RiwayatJabatanService;

    $service->syncRiwayatAktif($riwayatJabatan, $pegawai);

    expect($riwayatAktifSebelumnya->refresh()->is_aktif)->toBeFalse();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($riwayatJabatan->ref_jabatan_id);
    expect($pegawai->ref_unit_kerja_id)->toBe($riwayatJabatan->ref_unit_kerja_id);
});
