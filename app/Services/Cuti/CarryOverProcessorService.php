<?php

namespace App\Services\Cuti;

use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

class CarryOverProcessorService
{
    public function __construct(
        private SaldoLedgerService $saldoService,
    ) {}

    /**
     * Proses carry-over untuk satu pegawai.
     * Tahun N = tahun saat ini saat method dipanggil.
     */
    public function process(Pegawai $pegawai): void
    {
        $tahunN = (int) now()->year;
        $nip = $pegawai->nip;

        DB::transaction(function () use ($nip, $tahunN) {
            // Hanguskan sisa bucket N-2
            $this->expireBucket($nip, $tahunN - 2);

            // Batasi bucket N-1 maksimal 6 hari
            $this->capBucket($nip, $tahunN - 1, 6);

            // Kredit hak tahun N sebesar 12 hari
            $this->saldoService->kreditAlokasi($nip, 'CT', $tahunN, 12, 'Alokasi carry-over otomatis');
        });
    }

    /**
     * Hanguskan sisa saldo bucket tahun tertentu.
     */
    private function expireBucket(string $nip, int $tahun): void
    {
        $saldo = $this->saldoService->saldoBucket($nip, 'CT', $tahun);

        if ($saldo <= 0) {
            return;
        }

        // Cek idempotency: sudah ada expire row?
        $sudahExpire = CutiSaldoLedger::where('pegawai_nip', $nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', $tahun)
            ->where('jenis_transaksi', 'expire')
            ->whereNull('pengajuan_id')
            ->exists();

        if ($sudahExpire) {
            return;
        }

        CutiSaldoLedger::create([
            'pegawai_nip' => $nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => $tahun,
            'jenis_transaksi' => 'expire',
            'jumlah_hari' => -$saldo,
            'aktor_pegawai_nip' => $nip,
            'keterangan' => "Hangus carry-over tahun {$tahun}",
        ]);
    }

    /**
     * Batasi saldo bucket ke maksimum tertentu (kelebihan di-expire).
     */
    private function capBucket(string $nip, int $tahun, int $maxHari): void
    {
        $saldo = $this->saldoService->saldoBucket($nip, 'CT', $tahun);

        if ($saldo <= $maxHari) {
            return;
        }

        $kelebihan = $saldo - $maxHari;

        // Cek idempotency: sudah ada cap expire row?
        $sudahCap = CutiSaldoLedger::where('pegawai_nip', $nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', $tahun)
            ->where('jenis_transaksi', 'expire')
            ->where('keterangan', 'like', 'Cap carry-over%')
            ->exists();

        if ($sudahCap) {
            return;
        }

        CutiSaldoLedger::create([
            'pegawai_nip' => $nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => $tahun,
            'jenis_transaksi' => 'expire',
            'jumlah_hari' => -$kelebihan,
            'aktor_pegawai_nip' => $nip,
            'keterangan' => "Cap carry-over tahun {$tahun} max {$maxHari}",
        ]);
    }
}
