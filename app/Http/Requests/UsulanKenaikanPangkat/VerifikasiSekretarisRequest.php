<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class VerifikasiSekretarisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'setuju' => ['required', 'boolean'],
            'catatan' => ['required_if:setuju,false', 'string', 'max:500'],
        ];
    }
}
