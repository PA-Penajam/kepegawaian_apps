<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class SubmitUsulanKenaikanPangkatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checklist_submission_id' => ['nullable', 'exists:berkas_checklist_submissions,id'],
        ];
    }
}
