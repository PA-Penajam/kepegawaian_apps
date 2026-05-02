<?php

use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function createRiwayatJabatanFlashReferences(): array
{
    return [
        'jabatan' => RefJabatan::factory()->create(),
        'unitKerja' => RefUnitKerja::factory()->create(),
    ];
}

function makeRiwayatJabatanFlashPayload(array $overrides = []): array
{
    $references = createRiwayatJabatanFlashReferences();

    return array_merge([
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
        'no_sk' => fake()->unique()->numerify('SK-FLASH-####'),
        'tanggal_sk' => '2025-01-15',
        'tmt' => '2025-02-01',
        'pejabat_penetap' => 'Ketua Pengadilan',
        'is_aktif' => true,
        'keterangan' => 'Riwayat jabatan flash test',
    ], $overrides);
}

test('store riwayat jabatan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $payload = makeRiwayatJabatanFlashPayload();

    actingAs($user);

    $response = post(route('kepegawaian.pegawai.riwayat-jabatan.store', $pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));
});

test('update riwayat jabatan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => false,
    ]);
    $payload = makeRiwayatJabatanFlashPayload([
        'no_sk' => $riwayatJabatan->no_sk,
    ]);

    actingAs($user);

    $response = put(route('kepegawaian.pegawai.riwayat-jabatan.update', [$pegawai, $riwayatJabatan]), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));
});

test('destroy riwayat jabatan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    $response = delete(route('kepegawaian.pegawai.riwayat-jabatan.destroy', [$pegawai, $riwayatJabatan]));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));
});
