<?php

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;

describe('Pegawai', function () {
    it('can create a pegawai with all required fields', function () {
        $pegawai = Pegawai::query()->create([
            'nip' => '198510102009041001',
            'nama_lengkap' => 'Fulan Fulanah',
            'tempat_lahir' => 'Balikpapan',
            'tanggal_lahir' => '1985-10-10',
            'jenis_kelamin' => JenisKelamin::LakiLaki->value,
            'agama' => Agama::Islam->value,
            'status_perkawinan' => StatusPerkawinan::Kawin->value,
            'status_kepegawaian' => StatusKepegawaian::PNS->value,
            'status_pegawai' => StatusPegawai::Aktif->value,
            'tanggal_masuk' => '2009-04-01',
        ]);

        expect($pegawai->exists)->toBeTrue();
        expect($pegawai->nama_lengkap)->toBe('Fulan Fulanah');
    });

    it('has nullable nip for honorer', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => null,
            'status_kepegawaian' => StatusKepegawaian::Honorer->value,
        ]);

        expect($pegawai->nip)->toBeNull();
        expect($pegawai->status_kepegawaian)->toBe(StatusKepegawaian::Honorer);
    });

    it('casts enum fields correctly', function () {
        $pegawai = Pegawai::factory()->create([
            'jenis_kelamin' => JenisKelamin::Perempuan->value,
            'agama' => Agama::Islam->value,
            'status_pegawai' => StatusPegawai::Aktif->value,
        ]);

        expect($pegawai->jenis_kelamin)->toBe(JenisKelamin::Perempuan);
        expect($pegawai->agama)->toBe(Agama::Islam);
        expect($pegawai->status_pegawai)->toBe(StatusPegawai::Aktif);
    });

    it('belongs to ref_pangkat', function () {
        $pangkat = RefPangkat::factory()->create();

        $pegawai = Pegawai::factory()->create([
            'ref_pangkat_id' => $pangkat->id,
        ]);

        expect($pegawai->pangkat)->not->toBeNull();
        expect($pegawai->pangkat->is($pangkat))->toBeTrue();
    });

    it('belongs to ref_jabatan', function () {
        $jabatan = RefJabatan::factory()->create();

        $pegawai = Pegawai::factory()->create([
            'ref_jabatan_id' => $jabatan->id,
        ]);

        expect($pegawai->jabatan)->not->toBeNull();
        expect($pegawai->jabatan->is($jabatan))->toBeTrue();
    });

    it('belongs to ref_unit_kerja', function () {
        $unitKerja = RefUnitKerja::factory()->create();

        $pegawai = Pegawai::factory()->create([
            'ref_unit_kerja_id' => $unitKerja->id,
        ]);

        expect($pegawai->unitKerja)->not->toBeNull();
        expect($pegawai->unitKerja->is($unitKerja))->toBeTrue();
    });

    it('has iamRoles many-to-many relationship', function () {
        $pegawai = Pegawai::factory()->create();
        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
        $adminRole = IamRole::where('iam_application_id', $kepegawaian->id)
            ->where('slug', 'admin')->first();

        IamUserRole::firstOrCreate(
            ['user_id' => $pegawai->id, 'iam_role_id' => $adminRole->id],
            ['assigned_at' => now()]
        );

        $pegawai->refresh();

        expect($pegawai->iamRoles)->not->toBeEmpty();
        expect($pegawai->iamRoles->first()->is($adminRole))->toBeTrue();
    });

    it('scope aktif returns only active pegawai', function () {
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]);
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Pensiun->value]);

        expect(Pegawai::aktif()->count())->toBe(1);
    });

    it('scope by unit kerja filters correctly', function () {
        $unitKerjaA = RefUnitKerja::factory()->create();
        $unitKerjaB = RefUnitKerja::factory()->create();

        Pegawai::factory()->create(['ref_unit_kerja_id' => $unitKerjaA->id]);
        Pegawai::factory()->create(['ref_unit_kerja_id' => $unitKerjaB->id]);

        expect(Pegawai::byUnitKerja($unitKerjaA->id)->count())->toBe(1);
    });

    it('supports soft delete', function () {
        $pegawai = Pegawai::factory()->create();

        $pegawai->delete();

        expect($pegawai->trashed())->toBeTrue();
        expect(Pegawai::query()->find($pegawai->id))->toBeNull();
        expect(Pegawai::withTrashed()->find($pegawai->id))->not->toBeNull();
    });
});
