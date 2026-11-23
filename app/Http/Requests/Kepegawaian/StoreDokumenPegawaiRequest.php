<?php

namespace App\Http\Requests\Kepegawaian;

use App\Models\Pegawai;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pegawai = $this->route('pegawai');

        return $pegawai instanceof Pegawai
            && ($this->user()?->can('update', $pegawai) ?? false);
    }

    public function rules(): array
    {
        return [
            'ref_jenis_dokumen_id' => [
                'nullable',
                'ulid',
                Rule::exists('ref_jenis_dokumen', 'id')->whereNull('deleted_at'),
            ],
            'jenis_dokumen' => ['required_without:ref_jenis_dokumen_id', 'nullable', 'string', 'max:100'],
            'nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ref_jenis_dokumen_id.ulid' => 'Referensi jenis dokumen tidak valid.',
            'ref_jenis_dokumen_id.exists' => 'Referensi jenis dokumen tidak ditemukan.',
            'jenis_dokumen.required_without' => 'Jenis dokumen wajib diisi.',
            'jenis_dokumen.max' => 'Jenis dokumen maksimal 100 karakter.',
            'nomor_dokumen.max' => 'Nomor dokumen maksimal 100 karakter.',
            'tanggal_dokumen.date' => 'Tanggal dokumen harus berupa tanggal yang valid.',
            'file.required' => 'Berkas dokumen wajib diunggah.',
            'file.file' => 'Berkas dokumen harus berupa file yang valid.',
            'file.mimes' => 'Berkas dokumen hanya boleh berformat PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas dokumen maksimal 10 MB.',
        ];
    }
}
