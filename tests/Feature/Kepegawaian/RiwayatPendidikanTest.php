<?php

use App\Enums\JenjangPendidikan;
use App\Models\Pegawai;
use App\Models\RiwayatPendidikan;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function riwayatPendidikanIndexUrl(Pegawai $pegawai): string
{
    return route('kepegawaian.pegawai.riwayat-pendidikan.index', $pegawai);
}

function riwayatPendidikanItemUrl(string $action, Pegawai $pegawai, RiwayatPendidikan $riwayatPendidikan): string
{
    return route("kepegawaian.pegawai.riwayat-pendidikan.{$action}", [
        'pegawai' => $pegawai,
        'riwayat_pendidikan' => $riwayatPendidikan,
    ]);
}

function makeRiwayatPendidikanPayload(array $overrides = []): array
{
    return array_merge([
        'jenjang' => JenjangPendidikan::S1->value,
        'nama_sekolah' => 'Universitas Mulawarman',
        'jurusan' => 'Teknik Informatika',
        'tahun_lulus' => 2020,
        'no_ijazah' => 'IJZ-2020-001',
        'tanggal_ijazah' => '2020-08-17',
        'keterangan' => 'Lulus dengan predikat sangat memuaskan',
    ], $overrides);
}

test('guests are redirected to the login page for riwayat pendidikan routes', function () {
    $pegawai = Pegawai::factory()->create();

    get(riwayatPendidikanIndexUrl($pegawai))
        ->assertRedirect(route('login'));
});

test('viewers are forbidden from accessing the riwayat pendidikan page', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->create();

    actingAs($user);

    get(riwayatPendidikanIndexUrl($pegawai))
        ->assertForbidden();
});

test('admins can view all riwayat pendidikan for a pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->admin()->create();

    RiwayatPendidikan::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    get(riwayatPendidikanIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/riwayat-pendidikan')
            ->where('pegawai.id', $pegawai->id)
            ->has('riwayatPendidikan', 2)
            ->has('jenjangOptions', 10),
        );
});

test('operators can store riwayat pendidikan and jenjang is cast to enum', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $payload = makeRiwayatPendidikanPayload();

    actingAs($user);

    $response = post(riwayatPendidikanIndexUrl($pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPendidikanIndexUrl($pegawai));

    $riwayatPendidikan = RiwayatPendidikan::query()
        ->where('pegawai_id', $pegawai->id)
        ->firstWhere('nama_sekolah', $payload['nama_sekolah']);

    expect($riwayatPendidikan)->not->toBeNull();
    expect($riwayatPendidikan->jenjang)->toBe(JenjangPendidikan::S1);
    expect($riwayatPendidikan->pegawai->is($pegawai))->toBeTrue();
});

test('operators can update and soft delete riwayat pendidikan', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $riwayatPendidikan = RiwayatPendidikan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    $updatePayload = makeRiwayatPendidikanPayload([
        'jenjang' => JenjangPendidikan::S2->value,
        'nama_sekolah' => 'Universitas Indonesia',
        'tahun_lulus' => 2024,
    ]);

    actingAs($user);

    patch(riwayatPendidikanItemUrl('update', $pegawai, $riwayatPendidikan), $updatePayload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPendidikanIndexUrl($pegawai));

    expect($riwayatPendidikan->refresh()->nama_sekolah)->toBe('Universitas Indonesia');
    expect($riwayatPendidikan->jenjang)->toBe(JenjangPendidikan::S2);
    expect($riwayatPendidikan->tahun_lulus)->toBe(2024);

    delete(riwayatPendidikanItemUrl('destroy', $pegawai, $riwayatPendidikan))
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPendidikanIndexUrl($pegawai));

    expect(RiwayatPendidikan::query()->find($riwayatPendidikan->id))->toBeNull();
    expect(RiwayatPendidikan::query()->withTrashed()->find($riwayatPendidikan->id)?->trashed())->toBeTrue();
});

test('nama sekolah is required and jenjang must be a valid enum value', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();

    actingAs($user);

    from(riwayatPendidikanIndexUrl($pegawai))->post(riwayatPendidikanIndexUrl($pegawai), makeRiwayatPendidikanPayload([
        'nama_sekolah' => '',
        'jenjang' => 'invalid',
    ]))
        ->assertSessionHasErrors([
            'nama_sekolah',
            'jenjang',
        ])
        ->assertRedirect(riwayatPendidikanIndexUrl($pegawai));
});

test('pegawai has many riwayat pendidikan records', function () {
    $pegawai = Pegawai::factory()->create();

    RiwayatPendidikan::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    expect($pegawai->riwayatPendidikan)->toHaveCount(2);
});
