<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class StoreAplikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam-manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'alpha_dash', 'unique:iam_applications,slug'],
            'url' => ['required', 'url'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama aplikasi wajib diisi.',
            'slug.required' => 'Slug aplikasi wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh mengandung huruf, angka, strip, dan garis bawah.',
            'slug.unique' => 'Slug ini sudah digunakan oleh aplikasi lain.',
            'url.required' => 'URL aplikasi wajib diisi.',
            'url.url' => 'URL aplikasi harus berupa URL yang valid.',
        ];
    }
}
