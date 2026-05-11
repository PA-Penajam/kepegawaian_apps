<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class MintaPerbaikanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catatan' => ['required', 'string', 'max:1000'],
        ];
    }
}
