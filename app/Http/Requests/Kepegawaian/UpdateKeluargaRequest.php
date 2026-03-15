<?php

namespace App\Http\Requests\Kepegawaian;

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKeluargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hubungan' => ['required', Rule::enum(HubunganKeluarga::class)],
            'nama' => ['required', 'string', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', Rule::enum(JenisKelamin::class)],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
            'pendidikan' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'hubungan.required' => 'Hubungan keluarga wajib diisi.',
            'hubungan.enum' => 'Hubungan keluarga tidak valid.',
            'nama.required' => 'Nama keluarga wajib diisi.',
            'nama.max' => 'Nama keluarga maksimal 255 karakter.',
            'tempat_lahir.max' => 'Tempat lahir maksimal 255 karakter.',
            'tanggal_lahir.date' => 'Tanggal lahir harus berupa tanggal yang valid.',
            'jenis_kelamin.enum' => 'Jenis kelamin tidak valid.',
            'pekerjaan.max' => 'Pekerjaan maksimal 255 karakter.',
            'pendidikan.max' => 'Pendidikan maksimal 255 karakter.',
            'keterangan.string' => 'Keterangan harus berupa teks.',
        ];
    }
}
