<?php

namespace App\Http\Requests\Referensi;

use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', IamRole::class);
    }

    public function rules(): array
    {
        $appId = IamApplication::where('slug', 'kepegawaian')->value('id');

        return [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('iam_roles', 'nama')->where('iam_application_id', $appId)->whereNull('deleted_at'),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:iam_permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama role wajib diisi.',
            'nama.unique' => 'Nama role sudah ada di aplikasi ini.',
            'nama.max' => 'Nama role maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
