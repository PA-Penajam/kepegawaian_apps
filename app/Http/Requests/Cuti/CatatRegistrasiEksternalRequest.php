<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;

class CatatRegistrasiEksternalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.pengajuan.verify') ?? false;
    }

    /**
     * Aturan validasi pencatatan nomor registrasi aplikasi PTA/MA.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nomor_registrasi_eksternal' => ['required', 'string', 'max:100'],
            'tanggal_pengajuan_eksternal' => ['required', 'date'],
            'catatan_pengajuan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
