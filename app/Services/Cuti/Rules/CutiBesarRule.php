<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;

class CutiBesarRule implements CutiRule
{
    use HasMasaKerjaValidation;

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
     * Validasi minimal masa kerja 5 tahun dari TMT CPNS.
     */
    private function validateMasaKerja(CutiPengajuan $pengajuan): void
    {
        $this->validateMasaKerjaMinimum($pengajuan, self::MIN_MASA_KERJA);
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
