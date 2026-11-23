<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\RiwayatJabatan;
use Illuminate\Support\Facades\DB;

class RiwayatJabatanService
{
    public function store(Pegawai $pegawai, array $data): RiwayatJabatan
    {
        return DB::transaction(function () use ($pegawai, $data): RiwayatJabatan {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $willBeAktif = (bool) ($data['is_aktif'] ?? false);

            if ($willBeAktif) {
                RiwayatJabatan::query()
                    ->where('pegawai_id', $lockedPegawai->id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayatJabatan = $lockedPegawai->riwayatJabatan()->create($data);

            if ($riwayatJabatan->is_aktif) {
                $lockedPegawai->update([
                    'ref_jabatan_id' => $riwayatJabatan->ref_jabatan_id,
                    'ref_unit_kerja_id' => $riwayatJabatan->ref_unit_kerja_id,
                ]);
            }

            return $riwayatJabatan;
        });
    }

    public function update(RiwayatJabatan $riwayatJabatan, Pegawai $pegawai, array $data): RiwayatJabatan
    {
        return DB::transaction(function () use ($riwayatJabatan, $pegawai, $data): RiwayatJabatan {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasAktif = (bool) $riwayatJabatan->is_aktif;
            $willBeAktif = (bool) ($data['is_aktif'] ?? false);

            if ($willBeAktif) {
                RiwayatJabatan::query()
                    ->where('pegawai_id', $lockedPegawai->id)
                    ->where('id', '!=', $riwayatJabatan->id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayatJabatan->update($data);
            $riwayatJabatan = $riwayatJabatan->refresh();

            if ($riwayatJabatan->is_aktif) {
                $lockedPegawai->update([
                    'ref_jabatan_id' => $riwayatJabatan->ref_jabatan_id,
                    'ref_unit_kerja_id' => $riwayatJabatan->ref_unit_kerja_id,
                ]);
            } elseif ($wasAktif) {
                $this->resyncPegawaiJabatan($lockedPegawai);
            }

            return $riwayatJabatan;
        });
    }

    public function delete(RiwayatJabatan $riwayatJabatan, Pegawai $pegawai): void
    {
        DB::transaction(function () use ($riwayatJabatan, $pegawai): void {
            $lockedPegawai = Pegawai::query()
                ->whereKey($pegawai->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasAktif = (bool) $riwayatJabatan->is_aktif;

            if ($wasAktif) {
                $riwayatJabatan->is_aktif = false;
                $riwayatJabatan->save();
            }

            $riwayatJabatan->delete();

            if ($wasAktif) {
                $this->resyncPegawaiJabatan($lockedPegawai);
            }
        });
    }

    public function syncRiwayatAktif(RiwayatJabatan $riwayatJabatan, Pegawai $pegawai): void
    {
        RiwayatJabatan::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('id', '!=', $riwayatJabatan->id)
            ->update(['is_aktif' => false]);

        $pegawai->update([
            'ref_jabatan_id' => $riwayatJabatan->ref_jabatan_id,
            'ref_unit_kerja_id' => $riwayatJabatan->ref_unit_kerja_id,
        ]);
    }

    private function resyncPegawaiJabatan(Pegawai $pegawai): void
    {
        $aktif = RiwayatJabatan::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('is_aktif', true)
            ->latest('tmt')
            ->first();

        $pegawai->update([
            'ref_jabatan_id' => $aktif?->ref_jabatan_id,
            'ref_unit_kerja_id' => $aktif?->ref_unit_kerja_id,
        ]);
    }
}
