<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Http\FormRequest;

class UploadChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usulan = $this->route('usulan');

        return $usulan instanceof UsulanKenaikanPangkat
            && ($this->user()?->can('uploadChecklist', $usulan) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas checklist wajib dipilih.',
            'file.file' => 'Berkas checklist harus berupa file yang valid.',
            'file.mimes' => 'Format berkas checklist harus PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas checklist maksimal 10 MB.',
        ];
    }
}
