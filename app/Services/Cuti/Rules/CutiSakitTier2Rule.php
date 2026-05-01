<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use Carbon\Carbon;

class CutiSakitTier2Rule implements CutiRule
{
    /**
     * Minimal durasi kalender untuk cuti sakit tier 2.
     */
    private const MIN_DURASI_KALENDER = 15;

    /**
     * Maksimal durasi kalender untuk cuti sakit tier 2.
     */
    private const MAX_DURASI_KALENDER = 548;

    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CS_TIER2';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateDurasi($pengajuan);
        $this->validateLampiran($pengajuan);
    }

    /**
     * Validasi durasi antara 15-548 hari kalender.
     */
    private function validateDurasi(CutiPengajuan $pengajuan): void
    {
        $mulai = Carbon::parse($pengajuan->tanggal_mulai);
        $selesai = Carbon::parse($pengajuan->tanggal_selesai);
        $durasiKalender = $mulai->diffInDays($selesai) + 1;

        if ($durasiKalender < self::MIN_DURASI_KALENDER || $durasiKalender > self::MAX_DURASI_KALENDER) {
            throw new \DomainException(
                'Cuti sakit tier 2 harus antara '.self::MIN_DURASI_KALENDER.'-'.self::MAX_DURASI_KALENDER.' hari kalender.'
            );
        }
    }

    /**
     * Validasi lampiran wajib ada untuk cuti sakit tier 2.
     */
    private function validateLampiran(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->lampiran()->count() === 0) {
            throw new \DomainException('Cuti sakit tier 2 memerlukan lampiran surat keterangan dokter.');
        }
    }
}
