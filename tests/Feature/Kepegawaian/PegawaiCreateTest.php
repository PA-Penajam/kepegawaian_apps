<?php

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\JenjangPendidikan;
use App\Enums\Role;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Admin]);
});

it('can render the create page', function () {
    $this->actingAs($this->user)
        ->get(route('kepegawaian.pegawai.create'))
        ->assertSuccessful();
});

it('can create a new pegawai', function () {
    $unitKerja = RefUnitKerja::factory()->create();
    $jabatan = RefJabatan::factory()->create();
    $pangkat = RefPangkat::factory()->create();

    $data = [
        'nip' => '199001012020121001',
        'nama_lengkap' => 'John Doe',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        'agama' => Agama::Islam->value,
        'status_perkawinan' => StatusPerkawinan::BelumKawin->value,
        'golongan_darah' => GolonganDarah::O->value,
        'alamat' => 'Jl. Sudirman No. 1',
        'no_telepon' => '081234567890',
        'email' => 'john.doe@example.com',
        'status_kepegawaian' => StatusKepegawaian::PNS->value,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'tmt_cpns' => '2020-12-01',
        'tmt_pns' => '2021-12-01',
        'pendidikan_terakhir' => JenjangPendidikan::S1->value,
        'tanggal_masuk' => '2020-12-01',
        'ref_unit_kerja_id' => $unitKerja->id,
        'ref_jabatan_id' => $jabatan->id,
        'ref_pangkat_id' => $pangkat->id,
    ];

    $this->actingAs($this->user)
        ->post(route('kepegawaian.pegawai.store'), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('pegawai', [
        'nip' => '199001012020121001',
        'nama_lengkap' => 'John Doe',
        'email' => 'john.doe@example.com',
    ]);
});

it('validates required fields', function () {
    $this->actingAs($this->user)
        ->post(route('kepegawaian.pegawai.store'), [])
        ->assertSessionHasErrors([
            'nama_lengkap',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'agama',
            'status_perkawinan',
            'status_kepegawaian',
            'status_pegawai',
            'tanggal_masuk',
        ]);
});

it('rejects duplicate nip', function () {
    $pegawai = Pegawai::factory()->create(['nip' => '199001012020121001']);

    $data = Pegawai::factory()->make(['nip' => '199001012020121001'])->toArray();

    $this->actingAs($this->user)
        ->post(route('kepegawaian.pegawai.store'), $data)
        ->assertSessionHasErrors(['nip']);
});

it('rejects invalid enum values', function () {
    $data = Pegawai::factory()->make()->toArray();
    $data['jenis_kelamin'] = 'invalid';
    $data['agama'] = 'invalid';

    $this->actingAs($this->user)
        ->post(route('kepegawaian.pegawai.store'), $data)
        ->assertSessionHasErrors(['jenis_kelamin', 'agama']);
});

it('blocks unauthorized users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('kepegawaian.pegawai.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('kepegawaian.pegawai.store'), [])
        ->assertForbidden();
});
