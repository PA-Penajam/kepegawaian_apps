<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;

class AdjustSaldoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware iam.permission:cuti.saldo.adjust di route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pegawai_nip' => ['required', 'string', 'exists:pegawai,nip'],
            'jenis_cuti_kode' => ['required', 'string', 'exists:cuti_jenis_master,kode'],
            'tahun' => ['required', 'integer', 'min:2020'],
            'jumlah_hari' => ['required', 'integer'],
            'keterangan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pegawai_nip.required' => 'NIP pegawai wajib diisi.',
            'pegawai_nip.exists' => 'NIP pegawai tidak ditemukan.',
            'jenis_cuti_kode.required' => 'Jenis cuti wajib diisi.',
            'jenis_cuti_kode.exists' => 'Jenis cuti tidak dikenal.',
            'jumlah_hari.required' => 'Jumlah hari wajib diisi.',
            'keterangan.required' => 'Keterangan penyesuaian wajib diisi.',
            'keterangan.min' => 'Keterangan minimal 10 karakter.',
        ];
    }
}
