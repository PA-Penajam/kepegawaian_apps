<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHukumanDisiplinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ref_jenis_hukuman_disiplin_id' => ['nullable', 'exists:ref_jenis_hukuman_disiplin,id'],
            'no_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
            'tmt_berlaku' => ['required', 'date'],
            'tmt_selesai' => ['nullable', 'date', 'after_or_equal:tmt_berlaku'],
            'pelanggaran' => ['required', 'string'],
            'pejabat_penetap' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ref_jenis_hukuman_disiplin_id.exists' => 'Jenis hukuman disiplin yang dipilih tidak valid.',
            'no_sk.required' => 'Nomor SK wajib diisi.',
            'no_sk.max' => 'Nomor SK maksimal 100 karakter.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tanggal_sk.date' => 'Tanggal SK harus berupa tanggal yang valid.',
            'tmt_berlaku.required' => 'TMT berlaku wajib diisi.',
            'tmt_berlaku.date' => 'TMT berlaku harus berupa tanggal yang valid.',
            'tmt_selesai.date' => 'TMT selesai harus berupa tanggal yang valid.',
            'tmt_selesai.after_or_equal' => 'TMT selesai tidak boleh lebih awal dari TMT berlaku.',
            'pelanggaran.required' => 'Pelanggaran wajib diisi.',
            'pejabat_penetap.max' => 'Pejabat penetap maksimal 255 karakter.',
        ];
    }
}
