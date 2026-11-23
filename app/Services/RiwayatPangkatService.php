<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Illuminate\Support\Facades\DB;

class RiwayatPangkatService
{
    public function store(Pegawai $pegawai, array $data): RiwayatPangkat
    {
        return DB::transaction(function () use ($pegawai, $data): RiwayatPangkat {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $data['is_aktif'] = (bool) ($data['is_aktif'] ?? false);

            if ($data['is_aktif']) {
                RiwayatPangkat::query()
                    ->where('pegawai_id', $lockedPegawai->id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayatPangkat = $lockedPegawai->riwayatPangkat()->create($data);

            if ($riwayatPangkat->is_aktif) {
                $lockedPegawai->update([
                    'ref_pangkat_id' => $riwayatPangkat->ref_pangkat_id,
                ]);
            }

            return $riwayatPangkat;
        });
    }

    public function update(RiwayatPangkat $riwayatPangkat, Pegawai $pegawai, array $data): RiwayatPangkat
    {
        return DB::transaction(function () use ($riwayatPangkat, $pegawai, $data): RiwayatPangkat {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasAktif = (bool) $riwayatPangkat->is_aktif;
            $data['is_aktif'] = (bool) ($data['is_aktif'] ?? false);

            if ($data['is_aktif']) {
                RiwayatPangkat::query()
                    ->where('pegawai_id', $lockedPegawai->id)
                    ->where('id', '!=', $riwayatPangkat->id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayatPangkat->update($data);
            $riwayatPangkat = $riwayatPangkat->fresh();

            if ($riwayatPangkat->is_aktif) {
                $lockedPegawai->update([
                    'ref_pangkat_id' => $riwayatPangkat->ref_pangkat_id,
                ]);
            } elseif ($wasAktif) {
                $this->resyncPegawaiPangkat($lockedPegawai);
            }

            return $riwayatPangkat;
        });
    }

    public function delete(RiwayatPangkat $riwayatPangkat, Pegawai $pegawai): void
    {
        DB::transaction(function () use ($riwayatPangkat, $pegawai): void {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasAktif = (bool) $riwayatPangkat->is_aktif;

            if ($wasAktif) {
                $riwayatPangkat->is_aktif = false;
                $riwayatPangkat->save();
            }

            $riwayatPangkat->delete();

            if ($wasAktif) {
                $this->resyncPegawaiPangkat($lockedPegawai);
            }
        });
    }

    public function syncAktifRiwayatPangkat(Pegawai $pegawai, ?RiwayatPangkat $riwayatPangkat): void
    {
        if ($riwayatPangkat === null || ! $riwayatPangkat->is_aktif) {
            return;
        }

        RiwayatPangkat::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('id', '!=', $riwayatPangkat->id)
            ->update(['is_aktif' => false]);

        $pegawai->update([
            'ref_pangkat_id' => $riwayatPangkat->ref_pangkat_id,
        ]);
    }

    private function resyncPegawaiPangkat(Pegawai $pegawai): void
    {
        $aktif = RiwayatPangkat::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('is_aktif', true)
            ->latest('tmt')
            ->first();

        $pegawai->update([
            'ref_pangkat_id' => $aktif?->ref_pangkat_id,
        ]);
    }
}
