<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePenandatanganJenisCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.jenis.manage') ?? false;
    }

    /**
     * Aturan validasi pembaruan penandatangan surat izin per jenis cuti.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penandatangan_surat' => [
                'required',
                Rule::in(['ketua', 'wakil_ketua', 'panitera', 'sekretaris']),
            ],
        ];
    }

    /**
     * Pesan error berbahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penandatangan_surat.required' => 'Pejabat penandatangan surat wajib dipilih.',
            'penandatangan_surat.in' => 'Pilihan penandatangan tidak valid.',
        ];
    }
}
