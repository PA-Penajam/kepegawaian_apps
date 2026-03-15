<?php

namespace App\Http\Requests\Referensi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefStatusPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refStatusPegawai = $this->route('statusPegawai');

        return $this->user()->can('update', $refStatusPegawai);
    }

    public function rules(): array
    {
        $refStatusPegawai = $this->route('statusPegawai');

        return [
            'kode' => ['required', 'string', 'max:50', Rule::unique('ref_status_pegawai', 'kode')->ignore($refStatusPegawai->id)],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode wajib diisi.', 'kode.unique' => 'Kode sudah ada.', 'kode.max' => 'Kode maksimal 50 karakter.',
            'nama.required' => 'Nama wajib diisi.', 'nama.max' => 'Nama maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
