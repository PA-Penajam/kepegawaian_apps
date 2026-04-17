<?php

namespace App\Http\Requests\Kepegawaian;

use App\Models\PengajuanPerubahanData;
use Illuminate\Foundation\Http\FormRequest;

class RejectPengajuanPerubahanDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pengajuan = $this->route('pengajuan');

        return $this->user()?->hasPermission('pengajuan-perubahan.validate') ?? false
            && $pengajuan instanceof PengajuanPerubahanData
            && $pengajuan->status === \App\Enums\StatusPengajuanPerubahanData::Pending;
    }

    public function rules(): array
    {
        return [
            'alasan_penolakan' => ['required', 'string', 'min:10'],
        ];
    }
}
