<?php

namespace App\Http\Requests\Iam;

use App\Models\IamApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware iam.permission:iam.manage di route
    }

    /**
     * Slug TIDAK divalidasi di sini karena immutable — field slug yang
     * dikirim akan diabaikan di controller (tidak masuk validated()).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IamApplication $aplikasi */
        $aplikasi = $this->route('aplikasi');

        return [
            'nama' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'permission_ids' => ['array'],
            // Scope permission_ids hanya ke permissions milik aplikasi ini
            'permission_ids.*' => ['exists:iam_permissions,id', Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama role wajib diisi.',
        ];
    }
}
