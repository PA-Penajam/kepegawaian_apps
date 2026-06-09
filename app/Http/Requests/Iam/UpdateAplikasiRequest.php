<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAplikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama aplikasi wajib diisi.',
            'url.required' => 'URL aplikasi wajib diisi.',
            'url.url' => 'URL aplikasi harus berupa URL yang valid.',
        ];
    }
}
