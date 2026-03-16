<?php

namespace App\Http\Requests\Referensi;

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

        return [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ref_roles', 'nama')->ignore($refRole->id),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:ref_permissions,id'],
            'pegawai_ids' => ['nullable', 'array'],
            'pegawai_ids.*' => ['exists:pegawai,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama role wajib diisi.',
            'nama.unique' => 'Nama role sudah ada.',
            'nama.max' => 'Nama role maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
