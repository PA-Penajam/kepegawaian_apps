<?php

namespace App\Services\PengajuanPerubahanData;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RejectPengajuanPerubahanDataService
{
    public function handle(PengajuanPerubahanData $pengajuan, Pegawai $validator, string $alasan): void
    {
        DB::transaction(function () use ($pengajuan, $validator, $alasan): void {
            $locked = PengajuanPerubahanData::query()
                ->whereKey($pengajuan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== StatusPengajuanPerubahanData::Pending) {
                throw new \DomainException('Pengajuan perubahan data sudah diproses sebelumnya.');
            }

            if ($locked->pengaju_id === $validator->id || $locked->subject_pegawai_id === $validator->id) {
                throw new \DomainException('Validator tidak boleh menolak pengajuan perubahan data miliknya sendiri.');
            }

            $locked->update([
                'status' => StatusPengajuanPerubahanData::Rejected,
                'validator_id' => $validator->id,
                'rejected_at' => now(),
                'alasan_penolakan' => $alasan,
            ]);
        });

        Cache::forget('pending_pengajuan_count');
        $pengajuan->refresh();
    }
}
