<?php

use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function createPegawaiReferences(): array
{
    return [
        'pangkat' => RefPangkat::factory()->create(),
        'jabatan' => RefJabatan::factory()->create(),
        'unitKerja' => RefUnitKerja::factory()->create(),
    ];
}

function makePegawaiPayload(array $overrides = []): array
{
    $references = createPegawaiReferences();

    return Pegawai::factory()->raw(array_merge([
        'ref_pangkat_id' => $references['pangkat']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
    ], $overrides));
}

test('guests are redirected to the login page', function () {
    get(route('kepegawaian.pegawai.index'))
        ->assertRedirect(route('login'));
});

test('viewers are forbidden from accessing the pegawai index page', function () {
    $user = Pegawai::factory()->create();

    actingAs($user);

    get(route('kepegawaian.pegawai.index'))
        ->assertForbidden();
});

test('admins can view a paginated pegawai index with eager loaded relationships', function () {
    $user = Pegawai::factory()->admin()->create();
    $references = createPegawaiReferences();

    Pegawai::factory()->count(16)->create([
        'ref_pangkat_id' => $references['pangkat']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
    ]);

    actingAs($user);

    get(route('kepegawaian.pegawai.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/index')
            ->has('pegawai.data', 15)
            ->where('pegawai.total', 16)
            ->where('pegawai.per_page', 15)
            ->has('pegawai.data.0.pangkat')
            ->has('pegawai.data.0.jabatan')
            ->has('pegawai.data.0.unit_kerja'),
        );
});

test('admins can filter pegawai index by jabatan and receive jabatan options', function () {
    $user = Pegawai::factory()->admin()->create();
    $pangkat = RefPangkat::factory()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    $analisJabatan = RefJabatan::factory()->create([
        'nama' => 'Analis Kepegawaian',
    ]);
    $sekretarisJabatan = RefJabatan::factory()->create([
        'nama' => 'Sekretaris',
    ]);

    $pegawaiYangDicari = Pegawai::factory()->create([
        'nama_lengkap' => 'Pegawai Analis',
        'ref_pangkat_id' => $pangkat->id,
        'ref_jabatan_id' => $analisJabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
    ]);

    Pegawai::factory()->create([
        'nama_lengkap' => 'Pegawai Sekretaris',
        'ref_pangkat_id' => $pangkat->id,
        'ref_jabatan_id' => $sekretarisJabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
    ]);

    actingAs($user);

    get(route('kepegawaian.pegawai.index', ['jabatan' => $analisJabatan->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/index')
            ->where('filters.jabatan', $analisJabatan->id)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $pegawaiYangDicari->id)
            ->where('pegawai.data.0.jabatan.id', $analisJabatan->id)
            ->has('refJabatan', 2)
            ->where('refJabatan.0.id', $analisJabatan->id)
            ->where('refJabatan.1.id', $sekretarisJabatan->id),
        );
});

test('admins can sort pegawai index by jabatan name', function () {
    $user = Pegawai::factory()->admin()->create();
    $pangkat = RefPangkat::factory()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    $analisJabatan = RefJabatan::factory()->create([
        'nama' => 'Analis Kepegawaian',
    ]);
    $paniteraJabatan = RefJabatan::factory()->create([
        'nama' => 'Panitera',
    ]);
    $sekretarisJabatan = RefJabatan::factory()->create([
        'nama' => 'Sekretaris',
    ]);

    $pegawaiPanitera = Pegawai::factory()->create([
        'nama_lengkap' => 'Pegawai Panitera',
        'ref_pangkat_id' => $pangkat->id,
        'ref_jabatan_id' => $paniteraJabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
    ]);
    $pegawaiSekretaris = Pegawai::factory()->create([
        'nama_lengkap' => 'Pegawai Sekretaris',
        'ref_pangkat_id' => $pangkat->id,
        'ref_jabatan_id' => $sekretarisJabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
    ]);
    $pegawaiAnalis = Pegawai::factory()->create([
        'nama_lengkap' => 'Pegawai Analis',
        'ref_pangkat_id' => $pangkat->id,
        'ref_jabatan_id' => $analisJabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
    ]);

    actingAs($user);

    get(route('kepegawaian.pegawai.index', [
        'sort_by' => 'jabatan',
        'sort_dir' => 'asc',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/index')
            ->where('filters.sort_by', 'jabatan')
            ->where('filters.sort_dir', 'asc')
            ->where('pegawai.data.0.id', $pegawaiAnalis->id)
            ->where('pegawai.data.1.id', $pegawaiPanitera->id)
            ->where('pegawai.data.2.id', $pegawaiSekretaris->id),
        );

    get(route('kepegawaian.pegawai.index', [
        'sort_by' => 'jabatan',
        'sort_dir' => 'desc',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/index')
            ->where('filters.sort_by', 'jabatan')
            ->where('filters.sort_dir', 'desc')
            ->where('pegawai.data.0.id', $pegawaiSekretaris->id)
            ->where('pegawai.data.1.id', $pegawaiPanitera->id)
            ->where('pegawai.data.2.id', $pegawaiAnalis->id),
        );
});

test('operators can store a pegawai and are redirected to the detail page', function () {
    $user = Pegawai::factory()->operator()->create();
    $payload = makePegawaiPayload();

    actingAs($user);

    $response = post(route('kepegawaian.pegawai.store'), $payload);

    $pegawai = Pegawai::query()->firstWhere('nip', $payload['nip']);

    expect($pegawai)->not->toBeNull();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.show', $pegawai));
    expect($pegawai->nama_lengkap)->toBe($payload['nama_lengkap']);
});

test('operators must provide valid pegawai data when storing a pegawai', function () {
    $user = Pegawai::factory()->operator()->create();
    $payload = makePegawaiPayload([
        'nip' => '123',
        'jenis_kelamin' => 'invalid',
        'agama' => 'invalid',
        'status_perkawinan' => 'invalid',
        'golongan_darah' => 'invalid',
        'status_kepegawaian' => 'invalid',
        'status_pegawai' => 'invalid',
    ]);

    actingAs($user);
    from(route('kepegawaian.pegawai.create'));

    post(route('kepegawaian.pegawai.store'), $payload)
        ->assertSessionHasErrors([
            'nip',
            'jenis_kelamin',
            'agama',
            'status_perkawinan',
            'golongan_darah',
            'status_kepegawaian',
            'status_pegawai',
        ])
        ->assertRedirect(route('kepegawaian.pegawai.create'));
});

test('admins can view a pegawai detail page with loaded relationships', function () {
    $user = Pegawai::factory()->admin()->create();
    $references = createPegawaiReferences();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $references['pangkat']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
    ]);

    actingAs($user);

    get(route('kepegawaian.pegawai.show', $pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/show')
            ->where('pegawai.id', $pegawai->id)
            ->has('pegawai.pangkat')
            ->has('pegawai.jabatan')
            ->has('pegawai.unit_kerja'),
        );
});

test('operators can update a pegawai while keeping the same nip', function () {
    $user = Pegawai::factory()->operator()->create();
    $references = createPegawaiReferences();
    $pegawai = Pegawai::factory()->create([
        'ref_pangkat_id' => $references['pangkat']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
    ]);
    $payload = makePegawaiPayload([
        'nip' => $pegawai->nip,
        'nama_lengkap' => 'Nama Pegawai Diperbarui',
        'ref_pangkat_id' => $references['pangkat']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitKerja']->id,
    ]);

    actingAs($user);

    $response = put(route('kepegawaian.pegawai.update', $pegawai), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.show', $pegawai));

    expect($pegawai->refresh()->nama_lengkap)->toBe('Nama Pegawai Diperbarui');
    expect($pegawai->nip)->toBe($payload['nip']);
});

test('operators can soft delete a pegawai', function () {
    $user = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($user);

    $response = delete(route('kepegawaian.pegawai.destroy', $pegawai));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.index'));

    expect(Pegawai::query()->find($pegawai->id))->toBeNull();
    expect(Pegawai::query()->withTrashed()->find($pegawai->id)?->trashed())->toBeTrue();
});
