<?php

namespace App\Http\Requests\Cuti;

use App\Models\Cuti\CutiJenisMaster;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $rawJenisKode = $this->input('jenis_cuti_kode');
        $jenisKode = is_string($rawJenisKode) ? $rawJenisKode : '';
        $butuhLampiran = $jenisKode !== '' && (bool) CutiJenisMaster::where('kode', $jenisKode)
            ->where('aktif', true)
            ->value('butuh_lampiran');

        $lampiranRules = [
            Rule::requiredIf($butuhLampiran),
            'nullable',
            'array',
        ];
        if ($butuhLampiran) {
            $lampiranRules[] = 'min:1';
        }

        return [
            'jenis_cuti_kode' => [
                'required',
                'string',
                Rule::exists('cuti_jenis_master', 'kode')->where('aktif', true),
            ],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:tanggal_mulai',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $rawStart = $this->input('tanggal_mulai');
                    if (! is_string($rawStart) || ! is_string($value)) {
                        return;
                    }

                    try {
                        $start = Carbon::parse($rawStart);
                        $end = Carbon::parse($value);
                    } catch (\Throwable) {
                        return;
                    }

                    if ($start->year !== $end->year) {
                        $fail('Pengajuan tidak boleh lintas tahun.');
                    }
                },
            ],
            'alasan' => ['required', 'string', 'max:1000'],
            'alamat_selama_cuti' => ['nullable', 'string', 'max:500'],
            'nomor_telp_selama_cuti' => ['nullable', 'string', 'max:30'],
            'lampiran' => $lampiranRules,
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
            'jenis_cuti_kode.exists' => 'Jenis cuti tidak valid atau tidak aktif.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai harus hari ini atau setelahnya.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'alasan.required' => 'Alasan cuti wajib diisi.',
            'alasan.max' => 'Alasan cuti maksimal 1000 karakter.',
            'lampiran.required' => 'Lampiran pendukung wajib diunggah untuk jenis cuti ini.',
            'lampiran.array' => 'Format lampiran tidak valid.',
            'lampiran.min' => 'Minimal satu lampiran pendukung wajib diunggah.',
            'lampiran.*.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'lampiran.*.max' => 'Ukuran lampiran maksimal 5MB.',
        ];
    }
}
