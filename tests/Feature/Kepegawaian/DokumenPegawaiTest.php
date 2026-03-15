<?php

use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function dokumenPegawaiIndexUrl(Pegawai $pegawai): string
{
    return route('kepegawaian.pegawai.dokumen.index', $pegawai);
}

function dokumenPegawaiItemUrl(string $action, Pegawai $pegawai, DokumenPegawai $dokumenPegawai): string
{
    return route("kepegawaian.pegawai.dokumen.{$action}", [$pegawai, $dokumenPegawai]);
}

function makeDokumenPegawaiPayload(array $overrides = []): array
{
    return array_merge([
        'jenis_dokumen' => 'KTP',
        'nomor_dokumen' => '64xxxxxxxxxx',
        'tanggal_dokumen' => '2024-01-15',
        'file_path' => 'dokumen/pegawai/ktp.pdf',
        'keterangan' => 'Dokumen identitas utama',
    ], $overrides);
}

test('viewers are forbidden from accessing the dokumen pegawai page', function () {
    $pegawai = Pegawai::factory()->create();
    $user = User::factory()->create();

    actingAs($user);

    get(dokumenPegawaiIndexUrl($pegawai))
        ->assertForbidden();
});

test('admins can view all dokumen pegawai for a pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = User::factory()->admin()->create();

    DokumenPegawai::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    get(dokumenPegawaiIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/dokumen-pegawai')
            ->where('pegawai.id', $pegawai->id)
            ->has('dokumen', 2),
        );
});

test('operators can store dokumen pegawai and tanggal dokumen is cast to date', function () {
    $pegawai = Pegawai::factory()->create();
    $user = User::factory()->operator()->create();
    $payload = makeDokumenPegawaiPayload();

    actingAs($user);

    post(dokumenPegawaiIndexUrl($pegawai), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    $dokumenPegawai = DokumenPegawai::query()
        ->where('pegawai_id', $pegawai->id)
        ->firstWhere('jenis_dokumen', $payload['jenis_dokumen']);

    expect($dokumenPegawai)->not->toBeNull();
    expect($dokumenPegawai->pegawai->is($pegawai))->toBeTrue();
    expect($dokumenPegawai->tanggal_dokumen?->toDateString())->toBe('2024-01-15');
});

test('operators can update and soft delete dokumen pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = User::factory()->operator()->create();
    $dokumenPegawai = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    $updatePayload = makeDokumenPegawaiPayload([
        'jenis_dokumen' => 'NPWP',
        'nomor_dokumen' => '09.999.999.9-999.999',
        'tanggal_dokumen' => '2024-02-20',
    ]);

    actingAs($user);

    patch(dokumenPegawaiItemUrl('update', $pegawai, $dokumenPegawai), $updatePayload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    expect($dokumenPegawai->refresh()->jenis_dokumen)->toBe('NPWP');
    expect($dokumenPegawai->nomor_dokumen)->toBe('09.999.999.9-999.999');

    delete(dokumenPegawaiItemUrl('destroy', $pegawai, $dokumenPegawai))
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    expect(DokumenPegawai::query()->find($dokumenPegawai->id))->toBeNull();
    expect(DokumenPegawai::query()->withTrashed()->find($dokumenPegawai->id)?->trashed())->toBeTrue();
});

test('jenis dokumen is required when storing dokumen pegawai', function () {
    $pegawai = Pegawai::factory()->create();
    $user = User::factory()->operator()->create();

    actingAs($user);

    from(dokumenPegawaiIndexUrl($pegawai))->post(dokumenPegawaiIndexUrl($pegawai), makeDokumenPegawaiPayload([
        'jenis_dokumen' => '',
    ]))
        ->assertSessionHasErrors(['jenis_dokumen'])
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));
});

test('pegawai has many dokumen pegawai records', function () {
    $pegawai = Pegawai::factory()->create();

    DokumenPegawai::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    expect($pegawai->dokumenPegawai)->toHaveCount(2);
});
