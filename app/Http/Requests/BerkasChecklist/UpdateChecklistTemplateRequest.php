<?php

namespace App\Http\Requests\BerkasChecklist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChecklistTemplateRequest extends FormRequest
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
            'kode' => ['sometimes', 'string', 'max:50', Rule::in([$this->route('template')?->kode])],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'string', 'exists:berkas_checklist_items,id'],
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
            'kode.in' => 'Kode template tidak dapat diubah setelah dibuat.',
            'items.required' => 'Minimal satu item checklist harus diisi.',
            'items.min' => 'Minimal satu item checklist harus diisi.',
        ];
    }
}
