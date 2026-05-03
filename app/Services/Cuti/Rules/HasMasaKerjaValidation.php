<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use Carbon\Carbon;

trait HasMasaKerjaValidation
{
    /**
     * Validasi minimal masa kerja dari TMT CPNS.
     *
     * TODO: Validasi "terus-menerus" perlu data riwayat gap/ijin dari modul kepegawaian.
     * Untuk saat ini, menggunakan selisih tahun kalender dari TMT CPNS.
     */
    private function validateMasaKerjaMinimum(CutiPengajuan $pengajuan, int $minTahun): void
    {
        $tmtCpns = $pengajuan->pegawai->tmt_cpns;

        if (! $tmtCpns instanceof Carbon) {
            throw new \DomainException(
                'Data TMT CPNS pegawai tidak ditemukan. Silakan hubungi petugas kepegawaian.'
            );
        }

        $masaKerja = now()->diffInYears($tmtCpns);

        if ($masaKerja < $minTahun) {
            throw new \DomainException(
                'Memerlukan minimal '.$minTahun.' tahun masa kerja. Masa kerja saat ini: '.$masaKerja.' tahun.'
            );
        }
    }
}
