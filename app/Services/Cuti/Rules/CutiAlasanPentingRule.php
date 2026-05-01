<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;

class CutiAlasanPentingRule implements CutiRule
{
    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CAP';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateLampiran($pengajuan);
    }

    /**
     * Validasi lampiran wajib ada untuk cuti alasan penting.
     */
    private function validateLampiran(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->lampiran()->count() === 0) {
            throw new \DomainException('Cuti alasan penting memerlukan lampiran pendukung.');
        }
    }
}
