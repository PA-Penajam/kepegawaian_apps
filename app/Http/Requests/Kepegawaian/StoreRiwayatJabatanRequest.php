<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiwayatJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ref_jabatan_id' => ['nullable', 'ulid', 'exists:ref_jabatan,id'],
            'ref_unit_kerja_id' => ['nullable', 'ulid', 'exists:ref_unit_kerja,id'],
            'no_sk' => ['required', 'string', 'max:255'],
            'tanggal_sk' => ['required', 'date'],
            'tmt' => ['required', 'date'],
            'pejabat_penetap' => ['nullable', 'string', 'max:255'],
            'is_aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ref_jabatan_id.ulid' => 'Referensi jabatan tidak valid.',
            'ref_jabatan_id.exists' => 'Referensi jabatan tidak ditemukan.',
            'ref_unit_kerja_id.ulid' => 'Referensi unit kerja tidak valid.',
            'ref_unit_kerja_id.exists' => 'Referensi unit kerja tidak ditemukan.',
            'no_sk.required' => 'Nomor SK wajib diisi.',
            'no_sk.max' => 'Nomor SK maksimal 255 karakter.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tanggal_sk.date' => 'Tanggal SK harus berupa tanggal yang valid.',
            'tmt.required' => 'TMT wajib diisi.',
            'tmt.date' => 'TMT harus berupa tanggal yang valid.',
            'pejabat_penetap.max' => 'Pejabat penetap maksimal 255 karakter.',
            'is_aktif.required' => 'Status aktif wajib diisi.',
            'is_aktif.boolean' => 'Status aktif harus bernilai benar atau salah.',
            'keterangan.string' => 'Keterangan harus berupa teks.',
        ];
    }
}
