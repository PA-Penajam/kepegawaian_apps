<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class StoreDokumenPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => ['required', 'string', 'max:100'],
            'nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_dokumen.required' => 'Jenis dokumen wajib diisi.',
            'jenis_dokumen.max' => 'Jenis dokumen maksimal 100 karakter.',
            'nomor_dokumen.max' => 'Nomor dokumen maksimal 100 karakter.',
            'tanggal_dokumen.date' => 'Tanggal dokumen harus berupa tanggal yang valid.',
            'file_path.max' => 'File path maksimal 500 karakter.',
        ];
    }
}
