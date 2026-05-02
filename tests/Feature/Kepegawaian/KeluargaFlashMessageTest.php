<?php

use App\Enums\HubunganKeluarga;
use App\Models\Keluarga;
use App\Models\Pegawai;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function makeKeluargaFlashPayload(array $overrides = []): array
{
    return array_merge([
        'hubungan' => HubunganKeluarga::Istri->value,
        'nama' => 'Siti Aminah',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1990-05-20',
        'jenis_kelamin' => 'perempuan',
        'pekerjaan' => 'Ibu Rumah Tangga',
        'pendidikan' => 'S1',
        'keterangan' => 'Keluarga flash test',
    ], $overrides);
}

test('store keluarga mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    $payload = makeKeluargaFlashPayload();

    actingAs($user);

    $response = post(route('kepegawaian.pegawai.keluarga.store', $pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));
});

test('update keluarga mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);
    $payload = makeKeluargaFlashPayload();

    actingAs($user);

    $response = put(route('kepegawaian.pegawai.keluarga.update', [$pegawai, $keluarga]), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));
});

test('destroy keluarga mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    $response = delete(route('kepegawaian.pegawai.keluarga.destroy', [$pegawai, $keluarga]));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));
});
