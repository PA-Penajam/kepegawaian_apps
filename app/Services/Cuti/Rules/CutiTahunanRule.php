<?php

namespace App\Services\Cuti\Rules;

use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Exceptions\Cuti\SubmitTerlambatException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\SaldoLedgerService;
use Carbon\Carbon;

class CutiTahunanRule implements CutiRule
{
    /**
     * Minimal hari sebelum tanggal mulai untuk submit (H-3).
     */
    private const MIN_HARI_SEBELUM_MULAI = 3;

    /**
     * State pengajuan yang dianggap aktif (berlaku/sedang diproses).
     */
    private const STATE_AKTIF = [
        'DIAJUKAN',
        'DIVERIFIKASI',
        'DISETUJUI_ATASAN',
        'DISETUJUI',
    ];

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
        $this->validateTidakBertabrakanDenganCutiBesar($pengajuan);
        $this->validateSaldoCukup($pengajuan);
    }

    /**
     * Validasi cuti tahunan tidak bertabrakan dengan cuti besar (CB) aktif di tahun yang sama.
     */
    private function validateTidakBertabrakanDenganCutiBesar(CutiPengajuan $pengajuan): void
    {
        $tahunCuti = Carbon::parse($pengajuan->tanggal_mulai)->year;

        $adaCutiBesar = CutiPengajuan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CB')
            ->whereYear('tanggal_mulai', $tahunCuti)
            ->whereIn('state', self::STATE_AKTIF)
            ->where('id', '!=', $pengajuan->id)
            ->exists();

        if ($adaCutiBesar) {
            throw new \DomainException(
                'Cuti tahunan tidak dapat diajukan bersamaan dengan cuti besar di tahun yang sama.'
            );
        }
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
     * Validasi saldo aggregate cukup untuk jumlah hari kerja (termasuk carry-over tahun <= tahunHak).
     */
    private function validateSaldoCukup(CutiPengajuan $pengajuan): void
    {
        $tahun = $pengajuan->tahunHak();

        $alokasiBuckets = CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', '<=', $tahun)
            ->where('tahun_hak', '>=', $tahun - 1)
            ->orderBy('tahun_hak', 'asc')
            ->lockForUpdate()
            ->get();

        $bucketsInput = $alokasiBuckets->map(fn (CutiAlokasiTahunan $a): array => [
            'nip' => $pengajuan->pegawai_nip,
            'jenis' => 'CT',
            'tahun' => $a->tahun_hak,
        ])->all();

        $saldos = $this->saldoService->saldoBuckets($bucketsInput);
        $totalSaldo = 0;

        foreach ($alokasiBuckets as $bucket) {
            $bucketSaldo = $saldos["{$pengajuan->pegawai_nip}|CT|{$bucket->tahun_hak}"] ?? 0;
            if ($bucketSaldo > 0) {
                $totalSaldo += $bucketSaldo;
            }
        }

        if ($totalSaldo < $pengajuan->jumlah_hari_kerja) {
            throw new SaldoTidakCukupException(
                "Saldo cuti tahunan tidak mencukupi (tersedia: {$totalSaldo}, dibutuhkan: {$pengajuan->jumlah_hari_kerja})."
            );
        }
    }
}
