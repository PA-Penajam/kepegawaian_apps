<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function signInAsPegawaiAdmin(): void
{
    actingAs(User::factory()->admin()->create());
}

function createPegawaiFilterReferences(): array
{
    return [
        'pangkatA' => RefPangkat::factory()->create([
            'kode' => 'III/a',
            'nama' => 'Penata Muda',
            'golongan' => 'III',
            'ruang' => 'a',
            'urutan' => 31,
        ]),
        'pangkatB' => RefPangkat::factory()->create([
            'kode' => 'IV/a',
            'nama' => 'Pembina',
            'golongan' => 'IV',
            'ruang' => 'a',
            'urutan' => 41,
        ]),
        'jabatan' => RefJabatan::factory()->create([
            'nama' => 'Analis Kepegawaian',
        ]),
        'unitA' => RefUnitKerja::factory()->create([
            'nama' => 'Bagian Umum',
            'urutan' => 1,
        ]),
        'unitB' => RefUnitKerja::factory()->create([
            'nama' => 'Kepaniteraan',
            'urutan' => 2,
        ]),
    ];
}

function createPegawaiListEntry(array $references, array $overrides = []): Pegawai
{
    return Pegawai::factory()->create(array_merge([
        'nip' => fake()->unique()->numerify('##################'),
        'nama_lengkap' => fake('id_ID')->name(),
        'ref_pangkat_id' => $references['pangkatA']->id,
        'ref_jabatan_id' => $references['jabatan']->id,
        'ref_unit_kerja_id' => $references['unitA']->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $overrides));
}

test('search by nip exact match returns the matching pegawai', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nip' => '198501012009041001',
        'nama_lengkap' => 'Budi Santoso',
    ]);
    createPegawaiListEntry($references, [
        'nip' => '197912312008021002',
        'nama_lengkap' => 'Andi Wijaya',
    ]);

    get(route('kepegawaian.pegawai.index', ['search' => $target->nip]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/index')
            ->where('filters.search', $target->nip)
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('search by nama partial match returns matching pegawai', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nama_lengkap' => 'Siti Rahmawati',
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Budi Santoso',
    ]);

    get(route('kepegawaian.pegawai.index', ['search' => 'Rahma']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('filter by golongan uses ref pangkat kode', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Golongan Tiga',
        'ref_pangkat_id' => $references['pangkatA']->id,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Golongan Empat',
        'ref_pangkat_id' => $references['pangkatB']->id,
    ]);

    get(route('kepegawaian.pegawai.index', ['golongan' => 'III/a']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.golongan', 'III/a')
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('filter by unit kerja returns only matching pegawai', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Bagian Umum',
        'ref_unit_kerja_id' => $references['unitA']->id,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Kepaniteraan',
        'ref_unit_kerja_id' => $references['unitB']->id,
    ]);

    get(route('kepegawaian.pegawai.index', ['unit_kerja' => $references['unitA']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.unit_kerja', $references['unitA']->id)
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('filter by status pegawai returns only matching records', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Aktif',
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Pegawai Pensiun',
        'status_pegawai' => StatusPegawai::Pensiun->value,
    ]);

    get(route('kepegawaian.pegawai.index', ['status_pegawai' => StatusPegawai::Aktif->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status_pegawai', StatusPegawai::Aktif->value)
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('sort by nama ascending returns alphabetical order', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    createPegawaiListEntry($references, ['nama_lengkap' => 'Cici Lestari']);
    createPegawaiListEntry($references, ['nama_lengkap' => 'Andi Saputra']);
    createPegawaiListEntry($references, ['nama_lengkap' => 'Budi Hartono']);

    get(route('kepegawaian.pegawai.index', ['sort_by' => 'nama', 'sort_dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort_by', 'nama')
            ->where('filters.sort_dir', 'asc')
            ->where('pegawai.data.0.nama_lengkap', 'Andi Saputra')
            ->where('pegawai.data.1.nama_lengkap', 'Budi Hartono')
            ->where('pegawai.data.2.nama_lengkap', 'Cici Lestari'));
});

test('sort by nama descending returns reverse alphabetical order', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    createPegawaiListEntry($references, ['nama_lengkap' => 'Andi Saputra']);
    createPegawaiListEntry($references, ['nama_lengkap' => 'Cici Lestari']);
    createPegawaiListEntry($references, ['nama_lengkap' => 'Budi Hartono']);

    get(route('kepegawaian.pegawai.index', ['sort_by' => 'nama', 'sort_dir' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort_by', 'nama')
            ->where('filters.sort_dir', 'desc')
            ->where('pegawai.data.0.nama_lengkap', 'Cici Lestari')
            ->where('pegawai.data.1.nama_lengkap', 'Budi Hartono')
            ->where('pegawai.data.2.nama_lengkap', 'Andi Saputra'));
});

test('combined search and filter returns the correct subset', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    $target = createPegawaiListEntry($references, [
        'nama_lengkap' => 'Anita Rahma',
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_unit_kerja_id' => $references['unitA']->id,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Anita Pensiun',
        'status_pegawai' => StatusPegawai::Pensiun->value,
        'ref_unit_kerja_id' => $references['unitA']->id,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Budi Rahma',
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_unit_kerja_id' => $references['unitB']->id,
    ]);

    get(route('kepegawaian.pegawai.index', [
        'search' => 'Anita',
        'status_pegawai' => StatusPegawai::Aktif->value,
        'unit_kerja' => $references['unitA']->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 1)
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.id', $target->id));
});

test('empty search returns all pegawai and exposes filter options', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    createPegawaiListEntry($references, ['nama_lengkap' => 'Andi Saputra']);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Budi Hartono',
        'ref_unit_kerja_id' => $references['unitB']->id,
    ]);
    createPegawaiListEntry($references, [
        'nama_lengkap' => 'Cici Lestari',
        'ref_pangkat_id' => $references['pangkatB']->id,
    ]);

    get(route('kepegawaian.pegawai.index', ['search' => '']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 3)
            ->has('pegawai.data', 3)
            ->has('filterOptions.golongan', 2)
            ->has('filterOptions.unitKerja', 2)
            ->has('filterOptions.statusPegawai', 5));
});

test('search with no matching results returns an empty collection', function () {
    signInAsPegawaiAdmin();
    $references = createPegawaiFilterReferences();

    createPegawaiListEntry($references, ['nama_lengkap' => 'Andi Saputra']);
    createPegawaiListEntry($references, ['nama_lengkap' => 'Budi Hartono']);

    get(route('kepegawaian.pegawai.index', ['search' => 'Tidak Ada Pegawai']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 0)
            ->has('pegawai.data', 0));
});
