<?php

namespace App\Services\Cuti\Rules;

use App\Enums\JenisKelamin;
use App\Models\Cuti\CutiPengajuan;

class CutiMelahirkanRule implements CutiRule
{
    /**
     * Maksimal jumlah cuti melahirkan yang diperbolehkan.
     */
    private const MAX_ANAK = 3;

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
        return $jenisKode === 'CM';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateJenisKelamin($pengajuan);
        $this->validateBatasAnak($pengajuan);
    }

    /**
     * Validasi jenis kelamin harus perempuan.
     */
    private function validateJenisKelamin(CutiPengajuan $pengajuan): void
    {
        if ($pengajuan->pegawai->jenis_kelamin !== JenisKelamin::Perempuan) {
            throw new \DomainException(
                'Cuti melahirkan hanya dapat diajukan oleh pegawai berjenis kelamin perempuan.'
            );
        }
    }

    /**
     * Validasi maksimal 3 kali pengajuan cuti melahirkan.
     * Jika sudah mencapai batas, arahkan ke cuti besar.
     */
    private function validateBatasAnak(CutiPengajuan $pengajuan): void
    {
        $jumlahSudah = CutiPengajuan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CM')
            ->whereIn('state', self::STATE_AKTIF)
            ->where('id', '!=', $pengajuan->id)
            ->count();

        if ($jumlahSudah >= self::MAX_ANAK) {
            throw new \DomainException(
                'Anda sudah menggunakan cuti melahirkan sebanyak '.self::MAX_ANAK
                .' kali. Silakan gunakan cuti besar untuk pengajuan berikutnya.'
            );
        }
    }
}
