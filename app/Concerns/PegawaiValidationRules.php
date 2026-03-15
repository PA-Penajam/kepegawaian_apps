<?php

namespace App\Concerns;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Pegawai;
use Illuminate\Validation\Rule;

trait PegawaiValidationRules
{
    protected function pegawaiRules(?Pegawai $pegawai = null): array
    {
        return [
            'nip' => $this->nipRules($pegawai),
            'nip_lama' => ['nullable', 'string', 'max:255'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
            'agama' => ['required', Rule::enum(Agama::class)],
            'status_perkawinan' => ['required', Rule::enum(StatusPerkawinan::class)],
            'golongan_darah' => ['nullable', Rule::enum(GolonganDarah::class)],
            'alamat' => ['nullable', 'string'],
            'no_telepon' => ['nullable', 'string', 'max:255'],
            'email' => $this->emailRules($pegawai),
            'status_kepegawaian' => ['required', Rule::enum(StatusKepegawaian::class)],
            'status_pegawai' => ['required', Rule::enum(StatusPegawai::class)],
            'tmt_cpns' => ['nullable', 'date'],
            'tmt_pns' => ['nullable', 'date'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:255'],
            'tanggal_masuk' => ['required', 'date'],
            'tanggal_pensiun_bup' => ['nullable', 'date'],
            'ref_pangkat_id' => ['nullable', 'ulid', 'exists:ref_pangkat,id'],
            'ref_jabatan_id' => ['nullable', 'ulid', 'exists:ref_jabatan,id'],
            'ref_unit_kerja_id' => ['nullable', 'ulid', 'exists:ref_unit_kerja,id'],
            'no_karpeg' => ['nullable', 'string', 'max:255'],
            'no_karis_karsu' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:255'],
            'no_bpjs_kesehatan' => ['nullable', 'string', 'max:255'],
            'no_bpjs_ketenagakerjaan' => ['nullable', 'string', 'max:255'],
            'no_taspen' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    protected function nipRules(?Pegawai $pegawai = null): array
    {
        return [
            'nullable',
            'string',
            'size:18',
            'regex:/^[0-9]+$/',
            $pegawai === null
                ? Rule::unique('pegawai', 'nip')
                : Rule::unique('pegawai', 'nip')->ignore($pegawai),
        ];
    }

    protected function emailRules(?Pegawai $pegawai = null): array
    {
        return [
            'nullable',
            'string',
            'email',
            'max:255',
            $pegawai === null
                ? Rule::unique('pegawai', 'email')
                : Rule::unique('pegawai', 'email')->ignore($pegawai),
        ];
    }
}
