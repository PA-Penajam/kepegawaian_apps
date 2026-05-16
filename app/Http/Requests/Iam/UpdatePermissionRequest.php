<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Slug TIDAK divalidasi di sini karena immutable — gunakan endpoint
     * migrate-slug untuk rename. Field slug yang dikirim akan diabaikan
     * di controller (tidak ada di rules dan tidak masuk validated()).
     */
    public function rules(): array
    {
        return [
            'nama'       => ['required', 'string', 'min:3', 'max:100'],
            'group'      => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
