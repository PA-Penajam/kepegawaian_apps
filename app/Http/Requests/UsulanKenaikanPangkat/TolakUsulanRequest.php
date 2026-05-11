<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class TolakUsulanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:1000'],
        ];
    }
}
