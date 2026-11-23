<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class UploadSkFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sk_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'nomor_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date', 'before_or_equal:today'],
            'tmt_pangkat_baru' => ['required', 'date'],
            'masa_kerja_tahun' => ['required', 'integer', 'min:0'],
            'masa_kerja_bulan' => ['required', 'integer', 'between:0,11'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sk_file.required' => 'File SK wajib diunggah.',
            'sk_file.mimes' => 'File SK harus berformat PDF.',
            'sk_file.max' => 'Ukuran file SK maksimal 10 MB.',
            'nomor_sk.required' => 'Nomor SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tanggal_sk.before_or_equal' => 'Tanggal SK tidak boleh melebihi hari ini.',
            'tmt_pangkat_baru.required' => 'TMT pangkat baru wajib diisi.',
            'tmt_pangkat_baru.date' => 'Format TMT pangkat baru tidak valid.',
            'masa_kerja_tahun.required' => 'Masa kerja golongan (tahun) wajib diisi.',
            'masa_kerja_tahun.min' => 'Masa kerja golongan (tahun) tidak boleh negatif.',
            'masa_kerja_bulan.required' => 'Masa kerja golongan (bulan) wajib diisi.',
            'masa_kerja_bulan.between' => 'Masa kerja golongan (bulan) harus antara 0 sampai 11.',
        ];
    }
}
