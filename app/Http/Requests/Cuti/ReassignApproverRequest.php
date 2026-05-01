<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;

class ReassignApproverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.pengajuan.reassign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:petugas_kepegawaian,atasan_langsung,pejabat_berwenang'],
            'new_nip' => ['required', 'string', 'exists:pegawai,nip'],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role tidak valid.',
            'new_nip.required' => 'NIP pengganti wajib diisi.',
            'new_nip.exists' => 'NIP pengganti tidak ditemukan dalam database.',
            'alasan.required' => 'Alasan reassignment wajib diisi.',
            'alasan.min' => 'Alasan reassignment minimal 10 karakter.',
        ];
    }
}
