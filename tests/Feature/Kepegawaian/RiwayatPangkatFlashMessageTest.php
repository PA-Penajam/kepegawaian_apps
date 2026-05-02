<?php

use App\Models\Pegawai;
use App\Models\RefPangkat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function riwayatPangkatFlashIndexUrl(Pegawai $pegawai): string
{
    return "/kepegawaian/pegawai/{$pegawai->id}/riwayat-pangkat";
}

function riwayatPangkatFlashItemUrl(Pegawai $pegawai, string $riwayatId): string
{
    return riwayatPangkatFlashIndexUrl($pegawai)."/{$riwayatId}";
}

function makeRiwayatPangkatFlashPayload(RefPangkat $pangkat, array $overrides = []): array
{
    return array_merge([
        'ref_pangkat_id' => $pangkat->id,
        'no_sk' => 'SK-FLASH/PA.PNJ/III/2026',
        'tanggal_sk' => '2026-03-01',
        'tmt' => '2026-04-01',
        'pejabat_penetap' => 'Ketua Pengadilan Agama Penajam',
        'masa_kerja_tahun' => 10,
        'masa_kerja_bulan' => 4,
        'gaji_pokok' => 5250000.50,
        'is_aktif' => false,
        'keterangan' => 'Riwayat pangkat flash test',
    ], $overrides);
}

function insertRiwayatPangkatFlash(Pegawai $pegawai, RefPangkat $pangkat, array $overrides = []): string
{
    $id = (string) Str::ulid();

    DB::table('riwayat_pangkat')->insert(array_merge([
        'id' => $id,
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkat->id,
        'no_sk' => 'SK-INSERT-FLASH/PA.PNJ/III/2026',
        'tanggal_sk' => '2026-02-01',
        'tmt' => '2026-02-15',
        'pejabat_penetap' => 'Ketua Pengadilan Agama Penajam',
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 2,
        'gaji_pokok' => 4750000,
        'is_aktif' => false,
        'keterangan' => 'Data flash test',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

test('store riwayat pangkat mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();

    actingAs($user);

    $response = post(riwayatPangkatFlashIndexUrl($pegawai), makeRiwayatPangkatFlashPayload($pangkat));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(riwayatPangkatFlashIndexUrl($pegawai));
});

test('update riwayat pangkat mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();
    $riwayatId = insertRiwayatPangkatFlash($pegawai, $pangkat);

    actingAs($user);

    $response = put(riwayatPangkatFlashItemUrl($pegawai, $riwayatId), makeRiwayatPangkatFlashPayload($pangkat, [
        'no_sk' => 'SK-FLASH-UPD/PA.PNJ/III/2026',
    ]));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(riwayatPangkatFlashIndexUrl($pegawai));
});

test('destroy riwayat pangkat mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();
    $riwayatId = insertRiwayatPangkatFlash($pegawai, $pangkat);

    actingAs($user);

    $response = delete(riwayatPangkatFlashItemUrl($pegawai, $riwayatId));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(riwayatPangkatFlashIndexUrl($pegawai));
});
