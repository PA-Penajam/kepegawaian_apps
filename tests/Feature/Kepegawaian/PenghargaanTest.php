<?php

use App\Models\Pegawai;
use App\Models\Penghargaan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function makePenghargaanPayload(array $overrides = []): array
{
    return array_merge([
        'nama_penghargaan' => 'Satyalancana Karya Satya 10 Tahun',
        'no_sk' => 'SK-001/PA.PNJ/III/2026',
        'tanggal_sk' => '2026-03-10',
        'pejabat_penetap' => 'Ketua Pengadilan Agama Penajam',
        'keterangan' => 'Penghargaan hasil pengujian',
    ], $overrides);
}

test('dapat membuat penghargaan untuk pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    $penghargaan = Penghargaan::query()->create([
        'pegawai_id' => $pegawai->id,
        'nama_penghargaan' => 'Satyalancana Karya Satya 10 Tahun',
    ]);

    expect($penghargaan->nama_penghargaan)->toBe('Satyalancana Karya Satya 10 Tahun');
});

test('pegawai memiliki banyak penghargaan', function () {
    $pegawai = Pegawai::factory()->create();

    Penghargaan::query()->create([
        'pegawai_id' => $pegawai->id,
        'nama_penghargaan' => 'Award A',
    ]);

    Penghargaan::query()->create([
        'pegawai_id' => $pegawai->id,
        'nama_penghargaan' => 'Award B',
    ]);

    expect($pegawai->fresh()->penghargaan)->toHaveCount(2);
});

test('penghargaan dapat di-soft-delete', function () {
    $pegawai = Pegawai::factory()->create();

    $penghargaan = Penghargaan::query()->create([
        'pegawai_id' => $pegawai->id,
        'nama_penghargaan' => 'Award C',
    ]);

    $penghargaan->delete();

    expect(Penghargaan::query()->find($penghargaan->id))->toBeNull();
    expect(Penghargaan::query()->withTrashed()->find($penghargaan->id))->not->toBeNull();
});

test('admin dapat mengakses halaman penghargaan', function () {
    $admin = User::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    Penghargaan::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($admin);

    get(route('kepegawaian.pegawai.penghargaan.index', $pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/penghargaan')
            ->where('pegawai.id', $pegawai->id)
            ->has('penghargaan', 2),
        );
});

test('viewer tidak dapat mengakses penghargaan', function () {
    $viewer = User::factory()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($viewer);

    get(route('kepegawaian.pegawai.penghargaan.index', $pegawai))
        ->assertForbidden();
});

test('operator dapat menyimpan penghargaan baru', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($operator);

    post(route('kepegawaian.pegawai.penghargaan.store', $pegawai), makePenghargaanPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.penghargaan.index', $pegawai));

    expect(Penghargaan::query()
        ->where('pegawai_id', $pegawai->id)
        ->where('nama_penghargaan', 'Satyalancana Karya Satya 10 Tahun')
        ->exists())->toBeTrue();
});

test('operator dapat memperbarui penghargaan', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $penghargaan = Penghargaan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'nama_penghargaan' => 'Penghargaan Lama',
    ]);

    actingAs($operator);

    put(
        route('kepegawaian.pegawai.penghargaan.update', [$pegawai, $penghargaan]),
        makePenghargaanPayload([
            'nama_penghargaan' => 'Penghargaan Baru',
        ]),
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.penghargaan.index', $pegawai));

    expect($penghargaan->fresh()->nama_penghargaan)->toBe('Penghargaan Baru');
});

test('operator dapat menghapus penghargaan secara soft delete', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $penghargaan = Penghargaan::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($operator);

    delete(route('kepegawaian.pegawai.penghargaan.destroy', [$pegawai, $penghargaan]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.penghargaan.index', $pegawai));

    expect(Penghargaan::query()->find($penghargaan->id))->toBeNull();
    expect(Penghargaan::query()->withTrashed()->find($penghargaan->id)?->trashed())->toBeTrue();
});

test('validasi gagal jika nama penghargaan kosong', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($operator);

    post(route('kepegawaian.pegawai.penghargaan.store', $pegawai), makePenghargaanPayload([
        'nama_penghargaan' => '',
    ]))
        ->assertSessionHasErrors('nama_penghargaan');
});
