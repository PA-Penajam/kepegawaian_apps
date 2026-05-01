<?php

namespace App\Http\Requests\Cuti;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'jenis_cuti_kode' => ['required', 'in:CT,CS_TIER1,CS_TIER2,CAP'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:tanggal_mulai',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = Carbon::parse($this->input('tanggal_mulai'));
                    $end = Carbon::parse($value);
                    if ($start->year !== $end->year) {
                        $fail('Pengajuan tidak boleh lintas tahun.');
                    }
                },
            ],
            'alasan' => ['required', 'string', 'max:1000'],
            'alamat_selama_cuti' => ['nullable', 'string', 'max:500'],
            'nomor_telp_selama_cuti' => ['nullable', 'string', 'max:30'],
            'lampiran.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jenis_cuti_kode.required' => 'Jenis cuti wajib dipilih.',
            'jenis_cuti_kode.in' => 'Jenis cuti tidak valid.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai harus hari ini atau setelahnya.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'alasan.required' => 'Alasan cuti wajib diisi.',
            'alasan.max' => 'Alasan cuti maksimal 1000 karakter.',
            'lampiran.*.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'lampiran.*.max' => 'Ukuran lampiran maksimal 5MB.',
        ];
    }
}
