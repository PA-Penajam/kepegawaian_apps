<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRiwayatDiklatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ref_jenis_diklat_id' => ['nullable', 'exists:ref_jenis_diklat,id'],
            'nama_diklat' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jam_pelajaran' => ['nullable', 'integer', 'min:1'],
            'no_sertifikat' => ['nullable', 'string', 'max:100'],
            'tanggal_sertifikat' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_diklat.required' => 'Nama diklat wajib diisi.',
            'penyelenggara.required' => 'Penyelenggara wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'jam_pelajaran.min' => 'Jam pelajaran minimal 1.',
            'ref_jenis_diklat_id.exists' => 'Jenis diklat yang dipilih tidak valid.',
        ];
    }
}
