<?php

namespace App\Http\Requests\Iam;

use App\Models\IamApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware iam.permission:iam.manage di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IamApplication $aplikasi */
        $aplikasi = $this->route('aplikasi');

        return [
            'nama' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('iam_roles', 'slug')->where('iam_application_id', $aplikasi->id)],
            'keterangan' => ['nullable', 'string'],
            'permission_ids' => ['array'],
            // Scope permission_ids hanya ke permissions aktif milik aplikasi ini
            'permission_ids.*' => [
                'string',
                'ulid',
                Rule::exists('iam_permissions', 'id')
                    ->where('iam_application_id', $aplikasi->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama role wajib diisi.',
            'slug.required' => 'Slug role wajib diisi.',
            'slug.unique' => 'Slug role sudah digunakan di aplikasi ini.',
        ];
    }
}
