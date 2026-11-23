<?php

namespace App\Http\Requests\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsulanKenaikanPangkatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usulan = $this->route('usulan');

        return $usulan instanceof UsulanKenaikanPangkat
            && ($this->user()?->can('update', $usulan) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usulan = $this->route('usulan');

        return [
            'pegawai_id' => array_values(array_filter([
                'sometimes',
                'ulid',
                Rule::exists('pegawai', 'id')->whereNull('deleted_at'),
                $usulan instanceof UsulanKenaikanPangkat ? Rule::in([$usulan->pegawai_id]) : null,
            ])),
            'ref_pangkat_tujuan_id' => ['required', 'ulid', Rule::exists('ref_pangkat', 'id')->whereNull('deleted_at')],
            'periode_usul_bulan' => ['required', 'integer', 'between:1,12'],
            'periode_usul_tahun' => ['required', 'integer', 'min:'.date('Y')],
            'catatan_pengusul' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pegawai_id.ulid' => 'Format ID pegawai harus berupa ULID yang valid.',
            'pegawai_id.exists' => 'Data pegawai tidak ditemukan.',
            'pegawai_id.in' => 'Pegawai pada usulan tidak dapat diubah.',
            'ref_pangkat_tujuan_id.required' => 'Pangkat tujuan wajib dipilih.',
            'ref_pangkat_tujuan_id.ulid' => 'Format ID pangkat tujuan harus berupa ULID yang valid.',
            'ref_pangkat_tujuan_id.exists' => 'Data pangkat tujuan tidak ditemukan.',
            'periode_usul_bulan.required' => 'Periode bulan usul wajib diisi.',
            'periode_usul_bulan.between' => 'Periode bulan usul harus antara 1 sampai 12.',
            'periode_usul_tahun.required' => 'Periode tahun usul wajib diisi.',
            'periode_usul_tahun.min' => 'Periode tahun usul minimal tahun berjalan.',
        ];
    }
}
