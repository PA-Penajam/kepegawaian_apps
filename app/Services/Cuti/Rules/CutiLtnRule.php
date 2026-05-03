<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;

class CutiLtnRule implements CutiRule
{
    use HasMasaKerjaValidation;

    /**
     * Minimal masa kerja dalam tahun untuk CLTN.
     */
    private const MIN_MASA_KERJA = 5;

    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CLTN';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateMasaKerja($pengajuan);
        $this->validateLampiran($pengajuan);
    }

    /**
     * Validasi minimal masa kerja 5 tahun dari TMT CPNS.
     */
    private function validateMasaKerja(CutiPengajuan $pengajuan): void
    {
        $this->validateMasaKerjaMinimum($pengajuan, self::MIN_MASA_KERJA);
    }

    /**
     * Validasi lampiran wajib ada untuk CLTN.
     */
    private function validateLampiran(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->lampiran()->count() === 0) {
            throw new \DomainException(
                'Cuti lain tidak membayar gaji memerlukan lampiran pendukung.'
            );
        }
    }
}
