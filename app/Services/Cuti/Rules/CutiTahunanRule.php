<?php

namespace App\Services\Cuti\Rules;

use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Exceptions\Cuti\SubmitTerlambatException;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\SaldoLedgerService;
use Carbon\Carbon;

class CutiTahunanRule implements CutiRule
{
    /**
     * Minimal hari sebelum tanggal mulai untuk submit (H-3).
     */
    private const MIN_HARI_SEBELUM_MULAI = 3;

    public function __construct(
        private SaldoLedgerService $saldoService,
    ) {}

    public function applies(string $jenisKode): bool
    {
        return $jenisKode === 'CT';
    }

    public function validate(CutiPengajuan $pengajuan): void
    {
        $this->validateSubmitMinimal($pengajuan);
        $this->validateSaldoCukup($pengajuan);
    }

    /**
     * Validasi submit minimal H-3 sebelum tanggal mulai.
     */
    private function validateSubmitMinimal(CutiPengajuan $pengajuan): void
    {
        $tanggalMulai = Carbon::parse($pengajuan->tanggal_mulai)->startOfDay();
        $batasSubmit = Carbon::today()->addDays(self::MIN_HARI_SEBELUM_MULAI);

        if ($tanggalMulai->lt($batasSubmit)) {
            throw new SubmitTerlambatException(
                'Pengajuan cuti tahunan harus diajukan minimal H-3 sebelum tanggal mulai.'
            );
        }
    }

    /**
     * Validasi saldo aggregate cukup untuk jumlah hari kerja.
     */
    private function validateSaldoCukup(CutiPengajuan $pengajuan): void
    {
        $tahun = $pengajuan->tahunHak();
        $saldo = $this->saldoService->saldoBucket(
            $pengajuan->pegawai_nip,
            'CT',
            $tahun
        );

        if ($saldo < $pengajuan->jumlah_hari_kerja) {
            throw new SaldoTidakCukupException(
                "Saldo cuti tahunan tidak mencukupi (tersedia: {$saldo}, dibutuhkan: {$pengajuan->jumlah_hari_kerja})."
            );
        }
    }
}
