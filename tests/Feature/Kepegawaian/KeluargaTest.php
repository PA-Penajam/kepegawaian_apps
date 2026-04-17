<?php

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Models\Keluarga;
use App\Models\Pegawai;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function makeKeluargaPayload(array $overrides = []): array
{
    return array_merge([
        'hubungan' => 'Anak',
        'nama' => 'Anak Pertama',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-08-17',
        'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        'pekerjaan' => 'Pelajar',
        'pendidikan' => 'SD',
        'keterangan' => 'Tanggungan aktif',
    ], $overrides);
}

test('dapat membuat data keluarga untuk pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    $keluarga = Keluarga::query()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Istri',
        'nama' => 'Siti Aminah',
    ]);

    expect($keluarga->nama)->toBe('Siti Aminah');
    expect($keluarga->hubungan)->toBe(HubunganKeluarga::Istri);
});

test('pegawai memiliki banyak data keluarga', function () {
    $pegawai = Pegawai::factory()->create();

    Keluarga::query()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Anak',
        'nama' => 'Budi',
    ]);

    Keluarga::query()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Anak',
        'nama' => 'Sari',
    ]);

    expect($pegawai->fresh()->keluarga)->toHaveCount(2);
});

test('data keluarga dapat di-soft-delete', function () {
    $pegawai = Pegawai::factory()->create();

    $keluarga = Keluarga::query()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Suami',
        'nama' => 'Ahmad',
    ]);

    $keluarga->delete();

    expect(Keluarga::query()->find($keluarga->id))->toBeNull();
    expect(Keluarga::query()->withTrashed()->find($keluarga->id))->not->toBeNull();
});

test('admin dapat mengakses halaman keluarga', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    Keluarga::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($admin);

    get(route('kepegawaian.pegawai.keluarga.index', $pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/keluarga')
            ->where('pegawai.id', $pegawai->id)
            ->has('keluarga', 2),
        );
});

test('viewer tidak dapat mengakses keluarga', function () {
    $viewer = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($viewer);

    get(route('kepegawaian.pegawai.keluarga.index', $pegawai))
        ->assertForbidden();
});

test('operator dapat menyimpan data keluarga baru', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($operator);

    post(route('kepegawaian.pegawai.keluarga.store', $pegawai), makeKeluargaPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));

    // Operator sekarang harus melalui proses approval, jadi data tidak langsung tersimpan
    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $operator->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'anak',
        'aksi' => 'create',
        'status' => 'pending',
    ]);
});

test('operator dapat memperbarui data keluarga', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Anak',
        'nama' => 'Nama Lama',
    ]);

    actingAs($operator);

    patch(
        route('kepegawaian.pegawai.keluarga.update', [$pegawai, $keluarga]),
        makeKeluargaPayload([
            'hubungan' => 'IbuKandung',
            'nama' => 'Nama Baru',
        ]),
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));

    // Operator sekarang harus melalui proses approval, jadi data tidak langsung berubah
    expect($keluarga->fresh()->nama)->toBe('Nama Lama');

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $operator->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'orang_tua', // IbuKandung masuk ke domain orang_tua
        'aksi' => 'update',
        'status' => 'pending',
    ]);
});

test('operator dapat menghapus data keluarga secara soft delete', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Istri',
    ]);

    actingAs($operator);

    delete(route('kepegawaian.pegawai.keluarga.destroy', [$pegawai, $keluarga]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));

    // Operator sekarang harus melalui proses approval, jadi data tidak langsung terhapus
    expect(Keluarga::query()->find($keluarga->id))->not->toBeNull();

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $operator->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'pasangan', // Istri masuk ke domain pasangan
        'aksi' => 'delete',
        'status' => 'pending',
    ]);
});

test('validasi gagal jika hubungan tidak valid', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($operator);

    post(route('kepegawaian.pegawai.keluarga.store', $pegawai), makeKeluargaPayload([
        'hubungan' => 'INVALID_HUBUNGAN',
    ]))
        ->assertSessionHasErrors('hubungan');
});

it('operator update keluarga menjadi pending request alih-alih update langsung', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Anak',
        'nama' => 'Nama Lama Anak',
    ]);

    actingAs($operator)
        ->patch(route('kepegawaian.pegawai.keluarga.update', [$pegawai, $keluarga]), makeKeluargaPayload([
            'nama' => 'Nama Baru Anak',
        ]))
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));

    expect($keluarga->fresh()->nama)->toBe('Nama Lama Anak');

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $operator->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'anak',
        'status' => 'pending',
    ]);
});
