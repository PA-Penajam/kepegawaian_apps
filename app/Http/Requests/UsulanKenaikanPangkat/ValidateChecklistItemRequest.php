<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usulan = $this->route('usulan');

        return $usulan instanceof UsulanKenaikanPangkat
            && ($this->user()?->can('validateChecklist', $usulan) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(BerkasChecklistSubmissionItem::STATUSES)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status validasi berkas wajib dipilih.',
            'status.in' => 'Status validasi berkas tidak valid.',
            'catatan.max' => 'Catatan validasi maksimal 1000 karakter.',
        ];
    }
}
