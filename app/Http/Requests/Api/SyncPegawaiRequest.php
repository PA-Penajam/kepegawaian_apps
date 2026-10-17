<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi parameter ekspor sinkronisasi pegawai.
 *
 * Endpoint dilindungi auth:sanctum + verify.hmac, sehingga otorisasi
 * ditangani middleware; Form Request ini fokus pada validasi input.
 */
class SyncPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'since' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
