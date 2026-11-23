<?php

namespace App\Http\Requests\Kepegawaian;

use App\Models\PengajuanPerubahanData;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePengajuanPerubahanDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pengajuan = $this->route('pengajuan');

        return $this->user() !== null
            && $pengajuan instanceof PengajuanPerubahanData
            && $this->user()->can('approve', $pengajuan);
    }

    public function rules(): array
    {
        return [];
    }
}
