<?php

namespace App\Http\Requests\Cuti;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitSaldoRequest extends FormRequest
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
            'pegawai_nip' => ['required', 'string', Rule::exists('pegawai', 'nip')->whereNull('deleted_at')],
            'jenis_cuti_kode' => [
                'required',
                'string',
                Rule::exists('cuti_jenis_master', 'kode')
                    ->where('saldo_driven', true)
                    ->where('aktif', true),
            ],
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
            'jenis_cuti_kode.exists' => 'Jenis cuti tidak dikenal atau bukan jenis cuti berbasis saldo.',
            'jumlah_hari.required' => 'Jumlah hari wajib diisi.',
            'jumlah_hari.min' => 'Jumlah hari minimal 1.',
        ];
    }
}
