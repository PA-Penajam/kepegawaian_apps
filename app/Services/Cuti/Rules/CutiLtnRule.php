<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use Carbon\Carbon;

class CutiLtnRule implements CutiRule
{
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
     * Validasi minimal masa kerja 5 tahun dari tanggal pengangkatan (TMT CPNS).
     */
    private function validateMasaKerja(CutiPengajuan $pengajuan): void
    {
        $tmtCpns = $pengajuan->pegawai->tmt_cpns;

        if (! $tmtCpns instanceof Carbon) {
            throw new \DomainException(
                'Data TMT CPNS pegawai tidak ditemukan. Silakan hubungi petugas kepegawaian.'
            );
        }

        $masaKerja = now()->diffInYears($tmtCpns);

        if ($masaKerja < self::MIN_MASA_KERJA) {
            throw new \DomainException(
                'Cuti lain tidak membayar gaji memerlukan minimal '.self::MIN_MASA_KERJA
                .' tahun masa kerja. Masa kerja saat ini: '.$masaKerja.' tahun.'
            );
        }
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
