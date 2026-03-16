<?php

use App\Models\Pegawai;
use App\Models\RefPangkat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function riwayatPangkatIndexUrl(Pegawai $pegawai): string
{
    return "/kepegawaian/pegawai/{$pegawai->id}/riwayat-pangkat";
}

function riwayatPangkatItemUrl(Pegawai $pegawai, string $riwayatId): string
{
    return riwayatPangkatIndexUrl($pegawai)."/{$riwayatId}";
}

function makeRiwayatPangkatPayload(RefPangkat $pangkat, array $overrides = []): array
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

function insertRiwayatPangkat(Pegawai $pegawai, RefPangkat $pangkat, array $overrides = []): string
{
    $id = (string) Str::ulid();

    DB::table('riwayat_pangkat')->insert(array_merge([
        'id' => $id,
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkat->id,
        'no_sk' => 'SK-INSERT/PA.PNJ/III/2026',
        'tanggal_sk' => '2026-02-01',
        'tmt' => '2026-02-15',
        'pejabat_penetap' => 'Ketua Pengadilan Agama Penajam',
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 2,
        'gaji_pokok' => 4750000,
        'is_aktif' => false,
        'keterangan' => 'Data awal',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

dataset('riwayat-pangkat-allowed-users', [
    'admin' => [fn () => Pegawai::factory()->admin()->create()],
    'operator' => [fn () => Pegawai::factory()->operator()->create()],
]);

test('create riwayat pangkat with inactive status keeps pegawai pangkat unchanged', function () {
    $user = Pegawai::factory()->operator()->create();
    $currentPangkat = RefPangkat::factory()->create();
    $nextPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $currentPangkat->id,
    ]);

    actingAs($user);

    $response = post(riwayatPangkatIndexUrl($pegawai), makeRiwayatPangkatPayload($nextPangkat));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    $pegawai->refresh();

    expect($pegawai->ref_pangkat_id)->toBe($currentPangkat->id);

    expect(DB::table('riwayat_pangkat')
        ->where('pegawai_id', $pegawai->id)
        ->where('ref_pangkat_id', $nextPangkat->id)
        ->where('is_aktif', false)
        ->exists())->toBeTrue();
});

test('create active riwayat pangkat updates pegawai pangkat', function () {
    $user = Pegawai::factory()->operator()->create();
    $currentPangkat = RefPangkat::factory()->create();
    $nextPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $currentPangkat->id,
    ]);

    actingAs($user);

    $response = post(riwayatPangkatIndexUrl($pegawai), makeRiwayatPangkatPayload($nextPangkat, [
        'is_aktif' => true,
    ]));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    expect($pegawai->refresh()->ref_pangkat_id)->toBe($nextPangkat->id);

    expect(DB::table('riwayat_pangkat')
        ->where('pegawai_id', $pegawai->id)
        ->where('ref_pangkat_id', $nextPangkat->id)
        ->where('is_aktif', true)
        ->exists())->toBeTrue();
});

test('create second active riwayat pangkat deactivates previous active record', function () {
    $user = Pegawai::factory()->operator()->create();
    $firstPangkat = RefPangkat::factory()->create();
    $secondPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $firstPangkat->id,
    ]);
    $firstRiwayatId = insertRiwayatPangkat($pegawai, $firstPangkat, [
        'is_aktif' => true,
    ]);

    actingAs($user);

    $response = post(riwayatPangkatIndexUrl($pegawai), makeRiwayatPangkatPayload($secondPangkat, [
        'no_sk' => 'SK-002/PA.PNJ/III/2026',
        'is_aktif' => true,
    ]));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    expect($pegawai->refresh()->ref_pangkat_id)->toBe($secondPangkat->id);

    expect(DB::table('riwayat_pangkat')
        ->where('id', $firstRiwayatId)
        ->where('is_aktif', false)
        ->exists())->toBeTrue();
});

test('operators can update riwayat pangkat and sync active status', function () {
    $user = Pegawai::factory()->operator()->create();
    $firstPangkat = RefPangkat::factory()->create();
    $secondPangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $firstPangkat->id,
    ]);
    $activeRiwayatId = insertRiwayatPangkat($pegawai, $firstPangkat, [
        'is_aktif' => true,
    ]);
    $updatedRiwayatId = insertRiwayatPangkat($pegawai, $secondPangkat, [
        'no_sk' => 'SK-003/PA.PNJ/III/2026',
    ]);

    actingAs($user);

    $response = put(riwayatPangkatItemUrl($pegawai, $updatedRiwayatId), makeRiwayatPangkatPayload($secondPangkat, [
        'no_sk' => 'SK-004/PA.PNJ/III/2026',
        'is_aktif' => true,
    ]));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    expect($pegawai->refresh()->ref_pangkat_id)->toBe($secondPangkat->id);

    expect(DB::table('riwayat_pangkat')
        ->where('id', $activeRiwayatId)
        ->where('is_aktif', false)
        ->exists())->toBeTrue();

    expect(DB::table('riwayat_pangkat')
        ->where('id', $updatedRiwayatId)
        ->where('is_aktif', true)
        ->where('no_sk', 'SK-004/PA.PNJ/III/2026')
        ->exists())->toBeTrue();
});

test('soft delete keeps riwayat pangkat record as trashed', function () {
    $user = Pegawai::factory()->operator()->create();
    $pangkat = RefPangkat::factory()->create();
    $pegawai = Pegawai::factory()->create();
    $riwayatId = insertRiwayatPangkat($pegawai, $pangkat);

    actingAs($user);

    $response = delete(riwayatPangkatItemUrl($pegawai, $riwayatId));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    expect(DB::table('riwayat_pangkat')
        ->where('id', $riwayatId)
        ->whereNotNull('deleted_at')
        ->exists())->toBeTrue();
});

test('index returns success for admin and operator', function (Closure $makeUser) {
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();
    $riwayatId = insertRiwayatPangkat($pegawai, $pangkat, [
        'is_aktif' => true,
    ]);
    $user = $makeUser();

    actingAs($user);

    get(riwayatPangkatIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/riwayat-pangkat')
            ->where('pegawai.id', $pegawai->id)
            ->has('riwayatPangkat', 1)
            ->where('riwayatPangkat.0.id', $riwayatId)
            ->has('refPangkatOptions'),
        );
})->with('riwayat-pangkat-allowed-users');

test('store creates riwayat pangkat record and returns redirect response', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();

    actingAs($user);

    $response = post(riwayatPangkatIndexUrl($pegawai), makeRiwayatPangkatPayload($pangkat));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(riwayatPangkatIndexUrl($pegawai));

    expect(DB::table('riwayat_pangkat')
        ->where('pegawai_id', $pegawai->id)
        ->where('ref_pangkat_id', $pangkat->id)
        ->where('no_sk', 'SK-001/PA.PNJ/III/2026')
        ->exists())->toBeTrue();
});

test('viewer role is forbidden from riwayat pangkat page', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->create();

    actingAs($user);

    get(riwayatPangkatIndexUrl($pegawai))
        ->assertForbidden();
});
