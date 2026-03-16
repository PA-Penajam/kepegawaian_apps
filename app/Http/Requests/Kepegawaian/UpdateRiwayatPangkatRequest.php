<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiwayatPangkatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pegawai.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ref_pangkat_id' => ['nullable', 'exists:ref_pangkat,id'],
            'no_sk' => ['required', 'string', 'max:255'],
            'tanggal_sk' => ['required', 'date'],
            'tmt' => ['required', 'date'],
            'pejabat_penetap' => ['nullable', 'string', 'max:255'],
            'masa_kerja_tahun' => ['required', 'integer', 'min:0'],
            'masa_kerja_bulan' => ['required', 'integer', 'between:0,11'],
            'gaji_pokok' => ['nullable', 'numeric', 'min:0'],
            'is_aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ref_pangkat_id.exists' => 'Pangkat yang dipilih tidak valid.',
            'no_sk.required' => 'Nomor SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tanggal_sk.date' => 'Tanggal SK harus berupa tanggal yang valid.',
            'tmt.required' => 'TMT wajib diisi.',
            'tmt.date' => 'TMT harus berupa tanggal yang valid.',
            'masa_kerja_tahun.required' => 'Masa kerja tahun wajib diisi.',
            'masa_kerja_tahun.integer' => 'Masa kerja tahun harus berupa angka bulat.',
            'masa_kerja_tahun.min' => 'Masa kerja tahun tidak boleh kurang dari 0.',
            'masa_kerja_bulan.required' => 'Masa kerja bulan wajib diisi.',
            'masa_kerja_bulan.integer' => 'Masa kerja bulan harus berupa angka bulat.',
            'masa_kerja_bulan.between' => 'Masa kerja bulan harus di antara 0 sampai 11.',
            'gaji_pokok.numeric' => 'Gaji pokok harus berupa angka.',
            'gaji_pokok.min' => 'Gaji pokok tidak boleh kurang dari 0.',
            'is_aktif.boolean' => 'Status aktif harus berupa true atau false.',
        ];
    }
}
