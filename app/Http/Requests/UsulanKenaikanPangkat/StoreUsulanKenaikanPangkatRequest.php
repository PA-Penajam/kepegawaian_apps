<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsulanKenaikanPangkatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pegawai_id' => ['required', 'exists:pegawai,id', 'uuid'],
            'ref_pangkat_tujuan_id' => ['required', 'exists:ref_pangkat,id'],
            'periode_usul_bulan' => ['required', 'integer', 'between:1,12'],
            'periode_usul_tahun' => ['required', 'integer', 'min:'.date('Y')],
            'catatan_pengusul' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
