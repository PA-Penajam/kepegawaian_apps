<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Http\FormRequest;

class StoreLampiranUsulanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usulan = $this->route('usulan');

        return $usulan instanceof UsulanKenaikanPangkat
            && ($this->user()?->can('uploadLampiran', $usulan) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'jenis' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File lampiran wajib dipilih.',
            'file.file' => 'Lampiran harus berupa file yang valid.',
            'file.mimes' => 'Format lampiran harus PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran file lampiran maksimal 10 MB.',
        ];
    }
}
