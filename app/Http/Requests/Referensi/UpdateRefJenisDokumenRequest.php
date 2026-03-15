<?php

namespace App\Http\Requests\Referensi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefJenisDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refJenisDokumen = $this->route('jenis_dokuman');

        return $this->user()->can('update', $refJenisDokumen);
    }

    public function rules(): array
    {
        $refJenisDokumen = $this->route('jenis_dokuman');

        return [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ref_jenis_dokumen', 'nama')->ignore($refJenisDokumen->id),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis dokumen wajib diisi.',
            'nama.unique' => 'Nama jenis dokumen sudah ada.',
            'nama.max' => 'Nama jenis dokumen maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
