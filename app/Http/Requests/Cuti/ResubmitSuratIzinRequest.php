<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResubmitSuratIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.pengajuan.verify') ?? false;
    }

    /**
     * Aturan validasi perbaikan dan pengajuan ulang Surat Izin Cuti ke SiSukma.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_nama' => ['required', 'string', 'max:255'],
            'tujuan_instansi' => ['required', 'string', 'max:255'],
            'penandatangan' => ['sometimes', Rule::in(['ketua', 'wakil_ketua', 'panitera', 'sekretaris'])],
        ];
    }
}
