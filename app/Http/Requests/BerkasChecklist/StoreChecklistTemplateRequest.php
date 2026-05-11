<?php

namespace App\Http\Requests\BerkasChecklist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', Rule::unique('berkas_checklist_templates', 'kode')],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kode' => ['required', 'string', 'max:50'],
            'items.*.nama' => ['required', 'string', 'max:255'],
            'items.*.wajib' => ['boolean'],
            'items.*.urutan' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item checklist harus diisi.',
            'items.min' => 'Minimal satu item checklist harus diisi.',
        ];
    }
}
