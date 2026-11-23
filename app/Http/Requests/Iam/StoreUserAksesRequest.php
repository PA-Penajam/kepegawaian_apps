<?php

namespace App\Http\Requests\Iam;

use App\Models\IamRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserAksesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'iam_role_id' => [
                'bail',
                'required',
                'string',
                'ulid',
                Rule::exists('iam_roles', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Str::isUlid($value)) {
                        return;
                    }

                    // Pastikan aplikasi induk dari role masih aktif (belum di-soft-delete).
                    $hasActiveApplication = IamRole::query()
                        ->whereKey($value)
                        ->whereHas('application')
                        ->exists();

                    if (! $hasActiveApplication) {
                        $fail('Role yang dipilih tidak valid.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iam_role_id.required' => 'Role wajib dipilih.',
            'iam_role_id.exists' => 'Role yang dipilih tidak valid.',
        ];
    }
}
