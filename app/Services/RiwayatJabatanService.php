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
            $riwayatJabatan = $pegawai->riwayatJabatan()->create($data);

            if ($riwayatJabatan->is_aktif) {
                $this->syncRiwayatAktif($riwayatJabatan, $pegawai);
            }

            return $riwayatJabatan;
        });
    }

    public function update(RiwayatJabatan $riwayatJabatan, Pegawai $pegawai, array $data): RiwayatJabatan
    {
        return DB::transaction(function () use ($riwayatJabatan, $pegawai, $data): RiwayatJabatan {
            $riwayatJabatan->update($data);

            if ($riwayatJabatan->is_aktif) {
                $this->syncRiwayatAktif($riwayatJabatan, $pegawai);
            }

            return $riwayatJabatan->refresh();
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
}
