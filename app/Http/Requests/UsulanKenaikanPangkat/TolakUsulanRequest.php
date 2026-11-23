<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Http\FormRequest;

class TolakUsulanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usulan = $this->route('usulan');

        return $usulan instanceof UsulanKenaikanPangkat
            && ($this->user()?->can('tolak', $usulan) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penolakan wajib diisi.',
            'alasan.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
