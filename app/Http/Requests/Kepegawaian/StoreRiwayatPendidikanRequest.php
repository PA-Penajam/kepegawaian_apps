<?php

namespace App\Http\Requests\Kepegawaian;

use App\Enums\JenjangPendidikan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiwayatPendidikanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'jenjang' => ['required', Rule::enum(JenjangPendidikan::class)],
            'nama_sekolah' => ['required', 'string', 'max:255'],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'tahun_lulus' => ['required', 'integer', 'digits:4', 'min:1900', 'max:'.((int) now()->format('Y') + 1)],
            'no_ijazah' => ['nullable', 'string', 'max:255'],
            'tanggal_ijazah' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'jenjang.required' => 'Jenjang pendidikan wajib dipilih.',
            'jenjang.enum' => 'Jenjang pendidikan yang dipilih tidak valid.',
            'nama_sekolah.required' => 'Nama sekolah wajib diisi.',
            'tahun_lulus.required' => 'Tahun lulus wajib diisi.',
            'tahun_lulus.integer' => 'Tahun lulus harus berupa angka.',
            'tahun_lulus.digits' => 'Tahun lulus harus terdiri dari 4 digit.',
            'tahun_lulus.min' => 'Tahun lulus tidak valid.',
            'tahun_lulus.max' => 'Tahun lulus tidak valid.',
            'tanggal_ijazah.date' => 'Tanggal ijazah harus berupa tanggal yang valid.',
        ];
    }
}
