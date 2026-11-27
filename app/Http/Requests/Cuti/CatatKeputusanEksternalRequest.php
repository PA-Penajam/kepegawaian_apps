<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatatKeputusanEksternalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.pengajuan.verify') ?? false;
    }

    /**
     * Aturan validasi pencatatan keputusan akhir instansi atasan dengan SK Surat Masuk SiSukma.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keputusan' => ['required', Rule::in(['disetujui', 'ditolak'])],
            'sisukma_incoming_mail_public_id' => ['required', 'string', 'max:36'],
            'nomor_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'catatan_keputusan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
