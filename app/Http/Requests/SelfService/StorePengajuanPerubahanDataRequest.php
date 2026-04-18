<?php

namespace App\Http\Requests\SelfService;

use App\Enums\AksiPengajuan;
use App\Enums\DomainPengajuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengajuanPerubahanDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $targetType = $this->input('target_type');
        $targetId = $this->input('target_id');

        if ($targetType === 'pegawai' && $targetId && $user->id !== $targetId && ! $user->isOperator()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', Rule::enum(DomainPengajuan::class)],
            'aksi' => ['required', Rule::enum(AksiPengajuan::class)],
            'target_type' => ['required', 'in:pegawai,keluarga'],
            'target_id' => ['nullable', 'string'],
            'subject_pegawai_id' => ['nullable', 'string'],
            'after_payload' => ['required', 'array'],
            'lampiran' => ['array'],
            'lampiran.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $domain = $this->input('domain');
            $afterPayload = $this->input('after_payload', []);
            $lampiran = $this->file('lampiran', []);

            $wajibLampiran = $domain === 'pasangan'
                || $domain === 'anak'
                || ($domain === 'profil_pribadi' && count(array_intersect(
                    ['nama_lengkap', 'nik', 'tempat_lahir', 'tanggal_lahir', 'status_perkawinan'],
                    array_keys($afterPayload),
                )) > 0);

            if ($wajibLampiran && count($lampiran) === 0) {
                $validator->errors()->add('lampiran', 'Lampiran wajib diunggah untuk perubahan ini.');
            }
        });
    }
}
