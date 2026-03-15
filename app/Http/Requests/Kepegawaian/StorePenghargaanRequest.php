<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class StorePenghargaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ref_jenis_penghargaan_id' => ['nullable', 'exists:ref_jenis_penghargaan,id'],
            'nama_penghargaan' => ['required', 'string', 'max:255'],
            'no_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_sk' => ['nullable', 'date'],
            'pejabat_penetap' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ref_jenis_penghargaan_id.exists' => 'Jenis penghargaan yang dipilih tidak valid.',
            'nama_penghargaan.required' => 'Nama penghargaan wajib diisi.',
            'nama_penghargaan.max' => 'Nama penghargaan maksimal 255 karakter.',
            'no_sk.max' => 'Nomor SK maksimal 100 karakter.',
            'tanggal_sk.date' => 'Tanggal SK harus berupa tanggal yang valid.',
            'pejabat_penetap.max' => 'Pejabat penetap maksimal 255 karakter.',
            'keterangan.string' => 'Keterangan harus berupa teks.',
        ];
    }
}
