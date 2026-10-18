<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pertukaran SSO code menjadi token API.
 *
 * Endpoint dilindungi VerifyIamSignature + throttle ketat 10 req/menit,
 * sehingga otorisasi ditangani middleware; Form Request ini fokus
 * pada validasi input.
 */
class ExchangeCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode SSO wajib diisi.',
            'code.size' => 'Kode SSO tidak valid.',
        ];
    }
}
