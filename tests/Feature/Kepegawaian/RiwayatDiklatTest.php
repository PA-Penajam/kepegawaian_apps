<?php

use App\Models\Pegawai;
use App\Models\RefJenisDiklat;
use App\Models\RiwayatDiklat;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function riwayatDiklatIndexUrl(Pegawai $pegawai): string
{
    return sprintf('/kepegawaian/pegawai/%s/riwayat-diklat', $pegawai->id);
}

function riwayatDiklatItemUrl(Pegawai $pegawai, RiwayatDiklat|string $riwayatDiklat): string
{
    $id = $riwayatDiklat instanceof RiwayatDiklat
        ? $riwayatDiklat->id
        : $riwayatDiklat;

    return sprintf('/kepegawaian/pegawai/%s/riwayat-diklat/%s', $pegawai->id, $id);
}

function makeRiwayatDiklatPayload(array $overrides = []): array
{
    return array_merge([
        'ref_jenis_diklat_id' => null,
        'nama_diklat' => 'Diklat Kepemimpinan',
        'penyelenggara' => 'LAN',
        'tempat' => 'Samarinda',
        'tanggal_mulai' => '2024-03-01',
        'tanggal_selesai' => '2024-03-15',
        'jam_pelajaran' => 40,
        'no_sertifikat' => 'SER-2024-001',
        'tanggal_sertifikat' => '2024-03-20',
        'keterangan' => 'Peserta terbaik',
    ], $overrides);
}

test('guests are redirected to the login page for riwayat diklat routes', function () {
    $pegawai = Pegawai::factory()->create();

    get(riwayatDiklatIndexUrl($pegawai))
        ->assertRedirect(route('login'));
});

test('viewers are forbidden from accessing the riwayat diklat page', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->create();

    actingAs($user);

    get(riwayatDiklatIndexUrl($pegawai))
        ->assertForbidden();
});

test('admins can view all riwayat diklat for a pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->admin()->create();

    RiwayatDiklat::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    get(riwayatDiklatIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/riwayat-diklat')
            ->where('pegawai.id', $pegawai->id)
            ->has('riwayatDiklat', 2),
        );
});

test('operators can store riwayat diklat and it belongs to the selected pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $jenisDiklat = RefJenisDiklat::factory()->create();
    $payload = makeRiwayatDiklatPayload([
        'ref_jenis_diklat_id' => $jenisDiklat->id,
    ]);

    actingAs($user);

    $response = post(riwayatDiklatIndexUrl($pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatDiklatIndexUrl($pegawai));

    $riwayatDiklat = RiwayatDiklat::query()
        ->where('pegawai_id', $pegawai->id)
        ->firstWhere('nama_diklat', $payload['nama_diklat']);

    expect($riwayatDiklat)->not->toBeNull();
    expect($riwayatDiklat->pegawai->is($pegawai))->toBeTrue();
    expect($riwayatDiklat->jenisDiklat?->is($jenisDiklat))->toBeTrue();
});

test('tanggal selesai must be after or equal to tanggal mulai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();

    actingAs($user);
    from(riwayatDiklatIndexUrl($pegawai))
        ->post(riwayatDiklatIndexUrl($pegawai), makeRiwayatDiklatPayload([
            'tanggal_mulai' => '2024-03-15',
            'tanggal_selesai' => '2024-03-01',
        ]))
        ->assertSessionHasErrors(['tanggal_selesai'])
        ->assertRedirect(riwayatDiklatIndexUrl($pegawai));
});

test('operators can update riwayat diklat', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $riwayatDiklat = RiwayatDiklat::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    $payload = makeRiwayatDiklatPayload([
        'nama_diklat' => 'Diklat Fungsional',
        'penyelenggara' => 'BKN',
        'jam_pelajaran' => 60,
    ]);

    actingAs($user);
    patch(riwayatDiklatItemUrl($pegawai, $riwayatDiklat), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatDiklatIndexUrl($pegawai));

    expect($riwayatDiklat->refresh()->nama_diklat)->toBe('Diklat Fungsional');
    expect($riwayatDiklat->penyelenggara)->toBe('BKN');
    expect($riwayatDiklat->jam_pelajaran)->toBe(60);
});

test('operators can soft delete riwayat diklat', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $riwayatDiklat = RiwayatDiklat::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);
    delete(riwayatDiklatItemUrl($pegawai, $riwayatDiklat))
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatDiklatIndexUrl($pegawai));

    expect(RiwayatDiklat::query()->find($riwayatDiklat->id))->toBeNull();
    expect(RiwayatDiklat::query()->withTrashed()->find($riwayatDiklat->id)?->trashed())->toBeTrue();
});

test('pegawai has many riwayat diklat records', function () {
    $pegawai = Pegawai::factory()->create();

    RiwayatDiklat::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    expect($pegawai->riwayatDiklat)->toHaveCount(2);
});
