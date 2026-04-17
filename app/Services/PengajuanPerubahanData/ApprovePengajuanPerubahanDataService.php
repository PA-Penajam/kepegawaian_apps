<?php

namespace App\Services\PengajuanPerubahanData;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Illuminate\Support\Facades\DB;

class ApprovePengajuanPerubahanDataService
{
    public function handle(PengajuanPerubahanData $pengajuan, Pegawai $validator): void
    {
        DB::transaction(function () use ($pengajuan, $validator): void {
            if ($pengajuan->domain === 'profil_pribadi') {
                Pegawai::query()->findOrFail($pengajuan->target_id)->update($pengajuan->after_payload);
            }

            if (in_array($pengajuan->domain, ['pasangan', 'anak', 'orang_tua', 'keluarga_lain'], true)) {
                $this->applyKeluargaMutation($pengajuan);
            }

            $pengajuan->update([
                'status' => StatusPengajuanPerubahanData::Approved,
                'validator_id' => $validator->id,
                'approved_at' => now(),
            ]);
        });
    }

    private function applyKeluargaMutation(PengajuanPerubahanData $pengajuan): void
    {
        if ($pengajuan->aksi === 'create') {
            Pegawai::query()->findOrFail($pengajuan->subject_pegawai_id)
                ->keluarga()
                ->create($pengajuan->after_payload);

            return;
        }

        if ($pengajuan->aksi === 'update') {
            Keluarga::query()->findOrFail($pengajuan->target_id)->update($pengajuan->after_payload);

            return;
        }

        if ($pengajuan->aksi === 'delete') {
            Keluarga::query()->findOrFail($pengajuan->target_id)->delete();
        }
    }
}
