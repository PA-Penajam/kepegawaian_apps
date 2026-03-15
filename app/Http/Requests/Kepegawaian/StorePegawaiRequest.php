<?php

namespace App\Http\Requests\Kepegawaian;

use App\Concerns\PegawaiValidationRules;
use App\Models\Pegawai;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePegawaiRequest extends FormRequest
{
    use PegawaiValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pegawai::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->pegawaiRules();
    }

    public function messages(): array
    {
        return [
            'nip.size' => 'NIP harus terdiri dari 18 digit.',
            'nip.regex' => 'NIP harus berupa angka.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'email.unique' => 'Email pegawai sudah terdaftar.',
            'jenis_kelamin.enum' => 'Jenis kelamin tidak valid.',
            'agama.enum' => 'Agama tidak valid.',
            'status_perkawinan.enum' => 'Status perkawinan tidak valid.',
            'golongan_darah.enum' => 'Golongan darah tidak valid.',
            'status_kepegawaian.enum' => 'Status kepegawaian tidak valid.',
            'status_pegawai.enum' => 'Status pegawai tidak valid.',
            'ref_pangkat_id.exists' => 'Pangkat yang dipilih tidak valid.',
            'ref_jabatan_id.exists' => 'Jabatan yang dipilih tidak valid.',
            'ref_unit_kerja_id.exists' => 'Unit kerja yang dipilih tidak valid.',
        ];
    }
}
