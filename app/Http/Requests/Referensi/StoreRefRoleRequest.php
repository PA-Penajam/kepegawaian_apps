<?php

namespace App\Http\Requests\Referensi;

use App\Models\IamApplication;
use App\Models\IamRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRefRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', IamRole::class);
    }

    public function rules(): array
    {
        $appId = IamApplication::where('slug', config('iam.app_slug', 'kepegawaian'))->value('id');

        return [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('iam_roles', 'nama')->where('iam_application_id', $appId)->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail) use ($appId): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $slug = Str::slug($value);

                    if ($slug === '') {
                        $fail('Nama role harus mengandung huruf atau angka agar menghasilkan slug yang valid.');

                        return;
                    }

                    // Unique index (iam_application_id, slug) mencakup baris soft-deleted, sehingga validasi harus memakai withTrashed().
                    $slugExists = IamRole::withTrashed()
                        ->where('iam_application_id', $appId ?? '')
                        ->where('slug', $slug)
                        ->exists();

                    if ($slugExists) {
                        $fail('Slug dari nama role sudah digunakan di aplikasi ini.');
                    }
                },
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
