<?php

namespace App\Http\Requests\Iam;

use App\Models\IamApplication;
use App\Rules\ValidIamSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth dijalankan via middleware iam.permission di route
    }

    public function rules(): array
    {
        /** @var IamApplication $aplikasi */
        $aplikasi = $this->route('aplikasi');

        return [
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'slug' => [
                'required', 'string', 'max:120',
                new ValidIamSlug,
                Rule::unique('iam_permissions', 'slug')
                    ->where('iam_application_id', $aplikasi->id),
            ],
            'group'      => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-derive group dari segment pertama slug jika user tidak isi
        if (! $this->filled('group') && $this->filled('slug') && is_string($this->slug)) {
            $segments = explode('.', $this->slug);
            $this->merge(['group' => $segments[0]]);
        }
    }
}
