<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;

class InitSaldoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware iam.permission:cuti.saldo.view-all di route
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
            'jumlah_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:500'],
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
            'jumlah_hari.min' => 'Jumlah hari minimal 1.',
        ];
    }
}
