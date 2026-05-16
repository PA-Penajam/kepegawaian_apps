<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAksesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'iam_role_id' => ['required', 'exists:iam_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'iam_role_id.required' => 'Role wajib dipilih.',
            'iam_role_id.exists' => 'Role yang dipilih tidak valid.',
        ];
    }
}
