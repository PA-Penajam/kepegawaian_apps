<?php

namespace App\Http\Requests\Referensi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefStatusKepegawaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refStatusKepegawaian = $this->route('statusKepegawaian');

        return $this->user()->can('update', $refStatusKepegawaian);
    }

    public function rules(): array
    {
        $refStatusKepegawaian = $this->route('statusKepegawaian');

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('ref_status_kepegawaian', 'kode')->ignore($refStatusKepegawaian->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode status kepegawaian wajib diisi.',
            'kode.unique' => 'Kode status kepegawaian sudah ada.',
            'kode.max' => 'Kode status kepegawaian maksimal 50 karakter.',
            'nama.required' => 'Nama status kepegawaian wajib diisi.',
            'nama.max' => 'Nama status kepegawaian maksimal 255 karakter.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
