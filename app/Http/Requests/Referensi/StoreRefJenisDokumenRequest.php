<?php

namespace App\Http\Requests\Referensi;

use App\Models\RefJenisDokumen;
use Illuminate\Foundation\Http\FormRequest;

class StoreRefJenisDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RefJenisDokumen::class);
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis dokumen wajib diisi.',
            'nama.max' => 'Nama jenis dokumen maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
