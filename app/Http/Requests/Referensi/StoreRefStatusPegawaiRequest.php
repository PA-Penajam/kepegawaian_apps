<?php

namespace App\Http\Requests\Referensi;

use App\Models\RefStatusPegawai;
use Illuminate\Foundation\Http\FormRequest;

class StoreRefStatusPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RefStatusPegawai::class);
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:ref_status_pegawai,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode wajib diisi.', 'kode.max' => 'Kode maksimal 50 karakter.', 'kode.unique' => 'Kode sudah ada.',
            'nama.required' => 'Nama wajib diisi.', 'nama.max' => 'Nama maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
