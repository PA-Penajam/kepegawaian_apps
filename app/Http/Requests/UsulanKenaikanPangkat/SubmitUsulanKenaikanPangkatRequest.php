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
            'checklist_submission_id' => ['nullable', 'string', 'ulid', 'exists:berkas_checklist_submissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checklist_submission_id.string' => 'ID submission checklist harus berupa teks.',
            'checklist_submission_id.ulid' => 'Format ID submission checklist tidak valid.',
            'checklist_submission_id.exists' => 'Submission checklist tidak ditemukan.',
        ];
    }
}
