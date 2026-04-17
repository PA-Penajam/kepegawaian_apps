<?php

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\JenjangPendidikan;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = Pegawai::factory()->admin()->create();
    $this->pegawai = Pegawai::factory()->create();
});

it('can render the edit page', function () {
    $this->actingAs($this->user)
        ->get(route('kepegawaian.pegawai.edit', $this->pegawai))
        ->assertSuccessful();
});

it('can update an existing pegawai', function () {
    $unitKerja = RefUnitKerja::factory()->create();
    $jabatan = RefJabatan::factory()->create();
    $pangkat = RefPangkat::factory()->create();

    $data = [
        'nip' => '199001012020121002',
        'nama_lengkap' => 'Jane Doe',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '1992-02-02',
        'jenis_kelamin' => JenisKelamin::Perempuan->value,
        'agama' => Agama::Kristen->value,
        'status_perkawinan' => StatusPerkawinan::Kawin->value,
        'golongan_darah' => GolonganDarah::A->value,
        'alamat' => 'Jl. Thamrin No. 2',
        'no_telepon' => '081234567891',
        'email' => 'jane.doe@example.com',
        'status_kepegawaian' => StatusKepegawaian::PNS->value,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'tmt_cpns' => '2020-12-01',
        'tmt_pns' => '2021-12-01',
        'pendidikan_terakhir' => JenjangPendidikan::S2->value,
        'tanggal_masuk' => '2020-12-01',
        'ref_unit_kerja_id' => $unitKerja->id,
        'ref_jabatan_id' => $jabatan->id,
        'ref_pangkat_id' => $pangkat->id,
    ];

    $this->actingAs($this->user)
        ->put(route('kepegawaian.pegawai.update', $this->pegawai), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('pegawai', [
        'id' => $this->pegawai->id,
        'nip' => '199001012020121002',
        'nama_lengkap' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
    ]);
});

it('allows updating without changing unique fields', function () {
    $data = $this->pegawai->toArray();
    $data['nama_lengkap'] = 'Updated Name';

    $this->actingAs($this->user)
        ->put(route('kepegawaian.pegawai.update', $this->pegawai), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('pegawai', [
        'id' => $this->pegawai->id,
        'nama_lengkap' => 'Updated Name',
    ]);
});

it('rejects duplicate nip from other pegawai', function () {
    $otherPegawai = Pegawai::factory()->create(['nip' => '199001012020121003']);

    $data = $this->pegawai->toArray();
    $data['nip'] = '199001012020121003';

    $this->actingAs($this->user)
        ->put(route('kepegawaian.pegawai.update', $this->pegawai), $data)
        ->assertSessionHasErrors(['nip']);
});

it('blocks unauthorized users', function () {
    $user = Pegawai::factory()->create();

    $this->actingAs($user)
        ->get(route('kepegawaian.pegawai.edit', $this->pegawai))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('kepegawaian.pegawai.update', $this->pegawai), [])
        ->assertForbidden();
});

it('operator update biodata menjadi pending request alih-alih update langsung', function () {
    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Lama']);

    $this->actingAs($operator)
        ->put(route('kepegawaian.pegawai.update', $pegawai), array_merge([
            'nama_lengkap' => 'Nama Baru',
            'nik' => $pegawai->nik ?? '1234567890123456',
            'tempat_lahir' => $pegawai->tempat_lahir ?? 'Jakarta',
            'tanggal_lahir' => $pegawai->tanggal_lahir?->toDateString() ?? '1990-01-01',
            'jenis_kelamin' => $pegawai->getRawOriginal('jenis_kelamin') ?? 'laki-laki',
            'agama' => $pegawai->getRawOriginal('agama') ?? 'islam',
            'status_perkawinan' => 'belum_kawin',
            'alamat' => $pegawai->alamat ?? 'Alamat Test',
            'no_telepon' => $pegawai->no_telepon ?? '081234567890',
            'email' => $pegawai->email ?? 'test@example.com',
        ], [
            'status_kepegawaian' => $pegawai->getRawOriginal('status_kepegawaian') ?? 'pns',
            'status_pegawai' => $pegawai->getRawOriginal('status_pegawai') ?? 'aktif',
            'tanggal_masuk' => $pegawai->tanggal_masuk?->toDateString() ?? '2020-01-01',
        ]))
        ->assertRedirect(route('kepegawaian.pegawai.show', $pegawai));

    expect($pegawai->fresh()->nama_lengkap)->toBe('Nama Lama');

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $operator->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'profil_pribadi',
        'status' => 'pending',
    ]);
});
