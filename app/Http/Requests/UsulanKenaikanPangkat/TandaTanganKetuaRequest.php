<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class TandaTanganKetuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
