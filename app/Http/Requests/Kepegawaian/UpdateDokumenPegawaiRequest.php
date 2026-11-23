<?php

namespace App\Http\Requests\Kepegawaian;

class UpdateDokumenPegawaiRequest extends StoreDokumenPegawaiRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['file'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        return $rules;
    }
}
