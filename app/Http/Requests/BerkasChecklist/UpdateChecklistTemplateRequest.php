<?php

namespace App\Http\Requests\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistTemplate;
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
        $template = $this->route('template');
        $templateId = $template instanceof BerkasChecklistTemplate
            ? $template->id
            : (is_string($template) ? $template : null);

        return [
            'kode' => ['sometimes', 'string', 'max:50', Rule::in([$template?->kode])],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'list', 'min:1'],
            'items.*.id' => [
                'nullable',
                'string',
                Rule::exists('berkas_checklist_items', 'id')
                    ->where('berkas_checklist_template_id', $templateId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.kode' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
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
            'items.*.kode.distinct' => 'Kode item checklist tidak boleh duplikat.',
        ];
    }
}
