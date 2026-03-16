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
            $data['is_aktif'] = (bool) ($data['is_aktif'] ?? false);

            $riwayatPangkat = $pegawai->riwayatPangkat()->create($data);

            $this->syncAktifRiwayatPangkat($pegawai, $riwayatPangkat);

            return $riwayatPangkat;
        });
    }

    public function update(RiwayatPangkat $riwayatPangkat, Pegawai $pegawai, array $data): RiwayatPangkat
    {
        return DB::transaction(function () use ($riwayatPangkat, $pegawai, $data): RiwayatPangkat {
            $data['is_aktif'] = (bool) ($data['is_aktif'] ?? false);

            $riwayatPangkat->update($data);

            $riwayatPangkat = $riwayatPangkat->fresh();

            $this->syncAktifRiwayatPangkat($pegawai, $riwayatPangkat);

            return $riwayatPangkat;
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
}
