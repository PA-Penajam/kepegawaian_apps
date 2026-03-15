<?php

namespace App\Http\Requests\Referensi;

use App\Models\RefStatusKepegawaian;
use Illuminate\Foundation\Http\FormRequest;

class StoreRefStatusKepegawaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RefStatusKepegawaian::class);
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:ref_status_kepegawaian,kode'],
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
