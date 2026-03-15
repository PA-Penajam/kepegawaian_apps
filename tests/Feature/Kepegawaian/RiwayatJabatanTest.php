<?php

use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function createRiwayatJabatanReferences(): array
{
    return [
        'jabatan' => RefJabatan::factory()->create(),
        'unitKerja' => RefUnitKerja::factory()->create(),
    ];
}

function makeRiwayatJabatanPayload(array $overrides = []): array
{
    $references = createRiwayatJabatanReferences();

    return array_merge([
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
        'no_sk' => fake()->unique()->numerify('SK-####'),
        'tanggal_sk' => '2025-01-15',
        'tmt' => '2025-02-01',
        'pejabat_penetap' => 'Ketua Pengadilan',
        'is_aktif' => true,
        'keterangan' => 'Riwayat jabatan aktif',
    ], $overrides);
}

test('operator can create an active riwayat jabatan and sync pegawai jabatan and unit kerja', function () {
    $user = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $payload = makeRiwayatJabatanPayload();

    actingAs($user);

    $response = post(route('kepegawaian.pegawai.riwayat-jabatan.store', $pegawai), $payload);

    $riwayatJabatan = RiwayatJabatan::query()->firstWhere('no_sk', $payload['no_sk']);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));

    expect($riwayatJabatan)->not->toBeNull();
    expect($riwayatJabatan->is_aktif)->toBeTrue();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($payload['ref_jabatan_id']);
    expect($pegawai->ref_unit_kerja_id)->toBe($payload['ref_unit_kerja_id']);
});

test('creating a second active riwayat jabatan deactivates the previous active riwayat', function () {
    $user = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $firstPayload = makeRiwayatJabatanPayload([
        'no_sk' => 'SK-0001',
        'tanggal_sk' => '2025-01-01',
        'tmt' => '2025-01-01',
    ]);

    $secondPayload = makeRiwayatJabatanPayload([
        'no_sk' => 'SK-0002',
        'tanggal_sk' => '2025-03-01',
        'tmt' => '2025-03-01',
    ]);

    actingAs($user);

    post(route('kepegawaian.pegawai.riwayat-jabatan.store', $pegawai), $firstPayload)
        ->assertSessionHasNoErrors();

    $firstRiwayat = RiwayatJabatan::query()->firstWhere('no_sk', 'SK-0001');

    actingAs($user);

    post(route('kepegawaian.pegawai.riwayat-jabatan.store', $pegawai), $secondPayload)
        ->assertSessionHasNoErrors();

    $secondRiwayat = RiwayatJabatan::query()->firstWhere('no_sk', 'SK-0002');

    expect($firstRiwayat->refresh()->is_aktif)->toBeFalse();
    expect($secondRiwayat->is_aktif)->toBeTrue();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($secondPayload['ref_jabatan_id']);
    expect($pegawai->ref_unit_kerja_id)->toBe($secondPayload['ref_unit_kerja_id']);
});

test('operator can update riwayat jabatan to active and sync pegawai data', function () {
    $user = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $currentActive = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => false,
    ]);
    $payload = makeRiwayatJabatanPayload([
        'no_sk' => $riwayatJabatan->no_sk,
        'tanggal_sk' => '2025-04-01',
        'tmt' => '2025-04-01',
        'is_aktif' => true,
    ]);

    actingAs($user);

    $response = put(route('kepegawaian.pegawai.riwayat-jabatan.update', [$pegawai, $riwayatJabatan]), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));

    expect($currentActive->refresh()->is_aktif)->toBeFalse();
    expect($riwayatJabatan->refresh()->is_aktif)->toBeTrue();
    expect($pegawai->refresh()->ref_jabatan_id)->toBe($payload['ref_jabatan_id']);
    expect($pegawai->ref_unit_kerja_id)->toBe($payload['ref_unit_kerja_id']);
});

test('operator can soft delete a riwayat jabatan', function () {
    $user = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    $response = delete(route('kepegawaian.pegawai.riwayat-jabatan.destroy', [$pegawai, $riwayatJabatan]));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai));

    expect(RiwayatJabatan::query()->find($riwayatJabatan->id))->toBeNull();
    expect(RiwayatJabatan::query()->withTrashed()->find($riwayatJabatan->id)?->trashed())->toBeTrue();
});

test('admin can view the riwayat jabatan index page', function () {
    $user = User::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    RiwayatJabatan::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($user);

    get(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/riwayat-jabatan')
            ->where('pegawai.id', $pegawai->id)
            ->has('riwayatJabatan', 2)
            ->has('referensi.jabatan')
            ->has('referensi.unit_kerja'),
        );
});

test('viewer is forbidden from accessing the riwayat jabatan index page', function () {
    $user = User::factory()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($user);

    get(route('kepegawaian.pegawai.riwayat-jabatan.index', $pegawai))
        ->assertForbidden();
});
