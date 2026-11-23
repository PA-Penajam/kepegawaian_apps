<?php

namespace App\Http\Requests\Referensi;

use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refRole = $this->route('role');
        $appId = IamApplication::where('slug', config('iam.app_slug', 'kepegawaian'))->value('id');

        if (! $refRole instanceof IamRole || $appId === null || $refRole->iam_application_id !== $appId) {
            abort(404);
        }

        return $this->user()->can('update', $refRole);
    }

    public function rules(): array
    {
        $refRole = $this->route('role');
        $appId = IamApplication::where('slug', config('iam.app_slug', 'kepegawaian'))->value('id');

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
            'permissions.*' => [
                'string',
                'ulid',
                Rule::exists('iam_permissions', 'id')
                    ->where('iam_application_id', $appId ?? '')
                    ->whereNull('deleted_at'),
            ],
            'pegawai_ids' => ['nullable', 'array'],
            'pegawai_ids.*' => [
                'string',
                'ulid',
                Rule::exists('pegawai', 'id')->whereNull('deleted_at'),
            ],
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
