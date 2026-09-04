<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSyncConsumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                Rule::unique('sync_consumers', 'slug'),
            ],
            'base_url' => ['nullable', 'url', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama konsumen wajib diisi.',
            'slug.required' => 'Slug konsumen wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh mengandung huruf, angka, strip, dan garis bawah.',
            'slug.unique' => 'Slug ini sudah digunakan oleh konsumen lain.',
            'base_url.url' => 'Base URL harus berupa URL yang valid.',
        ];
    }
}
