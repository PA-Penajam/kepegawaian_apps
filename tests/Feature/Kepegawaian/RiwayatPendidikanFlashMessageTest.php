<?php

use App\Enums\JenjangPendidikan;
use App\Models\Pegawai;
use App\Models\RiwayatPendidikan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function makeRiwayatPendidikanFlashPayload(array $overrides = []): array
{
    return array_merge([
        'jenjang' => JenjangPendidikan::S1->value,
        'nama_sekolah' => 'Universitas Indonesia',
        'jurusan' => 'Ilmu Hukum',
        'tahun_lulus' => 2020,
        'no_ijazah' => 'IJZ-FLASH-001',
        'tanggal_ijazah' => '2020-08-15',
        'keterangan' => 'Pendidikan flash test',
    ], $overrides);
}

test('store riwayat pendidikan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $payload = makeRiwayatPendidikanFlashPayload();

    actingAs($user);

    $response = post(route('kepegawaian.pegawai.riwayat-pendidikan.store', $pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai));
});

test('update riwayat pendidikan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatPendidikan = RiwayatPendidikan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);
    $payload = makeRiwayatPendidikanFlashPayload();

    actingAs($user);

    $response = put(route('kepegawaian.pegawai.riwayat-pendidikan.update', [$pegawai, $riwayatPendidikan]), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai));
});

test('destroy riwayat pendidikan mengembalikan flash message sukses', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatPendidikan = RiwayatPendidikan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    $response = delete(route('kepegawaian.pegawai.riwayat-pendidikan.destroy', [$pegawai, $riwayatPendidikan]));

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai));
});
