<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;
use Carbon\Carbon;

class CutiBesarRule implements CutiRule
{
    /**
     * Durasi wajib cuti besar dalam hari kerja.
     */
    private const DURASI_WAJIB = 90;

    /**
     * Minimal masa kerja dalam tahun untuk cuti besar.
     */
    private const MIN_MASA_KERJA = 5;

    /**
     * State pengajuan yang dianggap aktif (berlaku/sedang diproses).
     */
    private const STATE_AKTIF = [
        'DIAJUKAN',
        'DIVERIFIKASI',
        'DISETUJUI_ATASAN',
        'DISETUJUI',
    ];

    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CB';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateDurasi($pengajuan);
        $this->validateMasaKerja($pengajuan);
        $this->validateTidakBertabrakanDenganCutiTahunan($pengajuan);
    }

    /**
     * Validasi durasi harus tepat 90 hari kerja.
     */
    private function validateDurasi(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->jumlah_hari_kerja !== self::DURASI_WAJIB) {
            throw new \DomainException(
                'Cuti besar harus tepat '.self::DURASI_WAJIB.' hari kerja, diajukan: '.$pengajuan->jumlah_hari_kerja.' hari.'
            );
        }
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
                'Cuti besar memerlukan minimal '.self::MIN_MASA_KERJA.' tahun masa kerja. Masa kerja saat ini: '.$masaKerja.' tahun.'
            );
        }
    }

    /**
     * Validasi cuti besar tidak bertabrakan dengan cuti tahunan (CT)
     * di tahun yang sama.
     */
    private function validateTidakBertabrakanDenganCutiTahunan(CutiPengajuan $pengajuan): void
    {
        $tahunCuti = $pengajuan->tanggal_mulai->year;

        $adaCutiTahunan = CutiPengajuan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->whereYear('tanggal_mulai', $tahunCuti)
            ->whereIn('state', self::STATE_AKTIF)
            ->where('id', '!=', $pengajuan->id)
            ->exists();

        if ($adaCutiTahunan) {
            throw new \DomainException(
                'Cuti besar tidak dapat diajukan bersamaan dengan cuti tahunan di tahun yang sama.'
            );
        }
    }
}
