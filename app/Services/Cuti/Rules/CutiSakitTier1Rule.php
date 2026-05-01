<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use Carbon\Carbon;

class CutiSakitTier1Rule implements CutiRule
{
    /**
     * Maksimal durasi kalender untuk cuti sakit tier 1.
     */
    private const MAX_DURASI_KALENDER = 14;

    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CS_TIER1';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateDurasi($pengajuan);
    }

    /**
     * Validasi durasi tidak melebihi 14 hari kalender.
     */
    private function validateDurasi(CutiPengajuan $pengajuan): void
    {
        $mulai = Carbon::parse($pengajuan->tanggal_mulai);
        $selesai = Carbon::parse($pengajuan->tanggal_selesai);
        $durasiKalender = $mulai->diffInDays($selesai) + 1;

        if ($durasiKalender > self::MAX_DURASI_KALENDER) {
            throw new \DomainException(
                "Cuti sakit tier 1 maksimal {self::MAX_DURASI_KALENDER} hari kalender, diajukan: {$durasiKalender} hari."
            );
        }
    }
}
