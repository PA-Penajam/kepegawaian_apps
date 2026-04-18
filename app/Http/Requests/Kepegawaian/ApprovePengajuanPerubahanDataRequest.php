<?php

namespace App\Http\Requests\Kepegawaian;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\PengajuanPerubahanData;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePengajuanPerubahanDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pengajuan = $this->route('pengajuan');

        return ($this->user()?->hasPermission('pengajuan-perubahan.validate') ?? false)
            && $pengajuan instanceof PengajuanPerubahanData
            && $pengajuan->status === StatusPengajuanPerubahanData::Pending;
    }

    public function rules(): array
    {
        return [];
    }
}
