<?php

namespace App\Http\Requests\Cuti;

use App\Enums\JenisJabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCutiKewenanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cuti.kewenangan.manage') ?? false;
    }

    /**
     * Aturan validasi tambah aturan matriks kewenangan cuti eksternal.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kewenanganId = $this->route('id');

        return [
            'jenis_cuti_kode' => ['required', 'string', 'exists:cuti_jenis_master,kode'],
            'jenis_jabatan' => [
                'required',
                Rule::enum(JenisJabatan::class),
                Rule::unique('cuti_kewenangan', 'jenis_jabatan')
                    ->where(fn ($query) => $query->where('jenis_cuti_kode', $this->input('jenis_cuti_kode')))
                    ->ignore($kewenanganId),
            ],
            'pemberi_izin' => ['required', Rule::in(['pta', 'ma'])],
            'instansi_nama' => ['required', 'string', 'max:150'],
            'nama_aplikasi' => ['nullable', 'string', 'max:100'],
            'url_aplikasi' => ['nullable', 'url', 'max:255'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
