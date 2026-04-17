<?php

namespace App\Services\PengajuanPerubahanData;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;

class RejectPengajuanPerubahanDataService
{
    public function handle(PengajuanPerubahanData $pengajuan, Pegawai $validator, string $alasan): void
    {
        $pengajuan->update([
            'status' => StatusPengajuanPerubahanData::Rejected,
            'validator_id' => $validator->id,
            'rejected_at' => now(),
            'alasan_penolakan' => $alasan,
        ]);
    }
}
