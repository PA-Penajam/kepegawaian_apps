<?php

namespace App\Http\Requests\Referensi;

use App\Models\IamApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refRole = $this->route('role');

        return $this->user()->can('update', $refRole);
    }

    public function rules(): array
    {
        $refRole = $this->route('role');
        $appId = IamApplication::where('slug', 'kepegawaian')->value('id');

        return [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('iam_roles', 'nama')
                    ->where('iam_application_id', $appId)
                    ->whereNull('deleted_at')
                    ->ignore($refRole->id),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:iam_permissions,id'],
            'pegawai_ids' => ['nullable', 'array'],
            'pegawai_ids.*' => ['exists:pegawai,id'],
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
