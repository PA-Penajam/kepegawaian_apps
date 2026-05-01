<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaldoLedgerService
{
    /**
     * Menghitung total saldo dari ledger untuk bucket tertentu.
     */
    public function saldoBucket(string $nip, string $jenisKode, int $tahun): int
    {
        return (int) CutiSaldoLedger::where('pegawai_nip', $nip)
            ->where('jenis_cuti_kode', $jenisKode)
            ->where('tahun_hak', $tahun)
            ->sum('jumlah_hari');
    }

    /**
     * Mengembalikan bucket alokasi tahunan yang masih memiliki saldo positif, terurut ASC.
     */
    public function bucketsAktif(string $nip, string $jenisKode): Collection
    {
        return CutiAlokasiTahunan::where('pegawai_nip', $nip)
            ->where('jenis_cuti_kode', $jenisKode)
            ->orderBy('tahun_hak', 'asc')
            ->get()
            ->filter(fn ($a) => $this->saldoBucket($nip, $jenisKode, $a->tahun_hak) > 0)
            ->values();
    }

    /**
     * Membuat debit pending dengan strategi FIFO lintas bucket.
     *
     * @return CutiSaldoLedger[]
     *
     * @throws SaldoTidakCukupException
     */
    public function debitPendingFifo(CutiPengajuan $p): array
    {
        $buckets = $this->bucketsAktif($p->pegawai_nip, $p->jenis_cuti_kode);
        $sisa = $p->jumlah_hari_kerja;
        $rows = [];

        foreach ($buckets as $bucket) {
            if ($sisa <= 0) {
                break;
            }

            $available = $this->saldoBucket($p->pegawai_nip, $p->jenis_cuti_kode, $bucket->tahun_hak);
            if ($available <= 0) {
                continue;
            }

            $ambil = min($sisa, $available);

            $rows[] = CutiSaldoLedger::create([
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => $p->jenis_cuti_kode,
                'tahun_hak' => $bucket->tahun_hak,
                'jenis_transaksi' => 'debit_pending',
                'jumlah_hari' => -$ambil,
                'pengajuan_id' => $p->id,
                'aktor_pegawai_nip' => $p->pegawai_nip,
            ]);

            $sisa -= $ambil;
        }

        if ($sisa > 0) {
            throw new SaldoTidakCukupException("Saldo {$p->jenis_cuti_kode} tidak mencukupi");
        }

        return $rows;
    }

    /**
     * Mengubah debit_pending menjadi debit_confirmed dengan void + confirmed per bucket.
     */
    public function commitConfirmed(CutiPengajuan $p): void
    {
        $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
            ->where('jenis_transaksi', 'debit_pending')
            ->get();

        foreach ($pendingRows as $pending) {
            // Void pending (flip sign ke positif)
            CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => $p->jenis_cuti_kode,
                'tahun_hak' => $pending->tahun_hak,
                'jenis_transaksi' => 'debit_void',
                'jumlah_hari' => -$pending->jumlah_hari,
                'aktor_pegawai_nip' => $p->pegawai_nip,
            ]);

            // Confirmed (nilai sama dengan pending, negatif)
            CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => $p->jenis_cuti_kode,
                'tahun_hak' => $pending->tahun_hak,
                'jenis_transaksi' => 'debit_confirmed',
                'jumlah_hari' => $pending->jumlah_hari,
                'aktor_pegawai_nip' => $p->pegawai_nip,
            ]);
        }
    }

    /**
     * Membatalkan debit_pending (void tanpa confirmed) untuk penolakan sebelum approval.
     */
    public function voidPending(CutiPengajuan $p): void
    {
        $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
            ->where('jenis_transaksi', 'debit_pending')
            ->get();

        foreach ($pendingRows as $pending) {
            CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => $p->jenis_cuti_kode,
                'tahun_hak' => $pending->tahun_hak,
                'jenis_transaksi' => 'debit_void',
                'jumlah_hari' => -$pending->jumlah_hari,
                'aktor_pegawai_nip' => $p->pegawai_nip,
            ]);
        }
    }

    /**
     * Memproses refund FIFO setelah cuti yang sudah disetujui dibatalkan.
     *
     * @return CutiSaldoLedger[]
     */
    public function processRefund(CutiPengajuan $p): array
    {
        $totalRefund = $this->hitungRefund($p);
        if ($totalRefund <= 0) {
            return [];
        }

        $confirmedRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
            ->where('jenis_transaksi', 'debit_confirmed')
            ->orderBy('tahun_hak', 'asc')
            ->get();

        $sisa = $totalRefund;
        $refundRows = [];

        foreach ($confirmedRows as $row) {
            if ($sisa <= 0) {
                break;
            }

            $confirmedDiBucket = abs($row->jumlah_hari);
            $refundDiBucket = min($sisa, $confirmedDiBucket);

            $refundRows[] = CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => $p->jenis_cuti_kode,
                'tahun_hak' => $row->tahun_hak,
                'jenis_transaksi' => 'kredit_refund',
                'jumlah_hari' => +$refundDiBucket,
                'aktor_pegawai_nip' => $p->pegawai_nip,
            ]);

            $sisa -= $refundDiBucket;
        }

        return $refundRows;
    }

    /**
     * Membuat alokasi tahunan dan kredit ledger secara idempotent.
     */
    public function kreditAlokasi(string $nip, string $jenisKode, int $tahun, int $hari, string $keterangan): void
    {
        DB::transaction(function () use ($nip, $jenisKode, $tahun, $hari, $keterangan) {
            // Buat atau ambil anchor row
            $alokasi = CutiAlokasiTahunan::firstOrCreate(
                ['pegawai_nip' => $nip, 'jenis_cuti_kode' => $jenisKode, 'tahun_hak' => $tahun],
                ['hak_awal' => $hari]
            );

            // Lock anchor untuk mencegah race condition
            DB::table('cuti_alokasi_tahunan')->where('id', $alokasi->id)->lockForUpdate()->first();

            // Cek apakah kredit inisialisasi sudah ada (idempotency)
            $sudahKredit = CutiSaldoLedger::where('pegawai_nip', $nip)
                ->where('jenis_cuti_kode', $jenisKode)
                ->where('tahun_hak', $tahun)
                ->where('jenis_transaksi', 'kredit')
                ->whereNull('pengajuan_id')
                ->exists();

            if ($sudahKredit) {
                return;
            }

            CutiSaldoLedger::create([
                'pegawai_nip' => $nip,
                'jenis_cuti_kode' => $jenisKode,
                'tahun_hak' => $tahun,
                'jenis_transaksi' => 'kredit',
                'jumlah_hari' => $hari,
                'aktor_pegawai_nip' => $nip,
                'keterangan' => $keterangan,
            ]);
        });
    }

    /**
     * Melakukan penyesuaian saldo (kredit atau debit manual oleh admin).
     */
    public function penyesuaian(string $nip, string $jenisKode, int $tahun, int $jumlahHari, string $keterangan, string $aktorNip): void
    {
        DB::transaction(function () use ($nip, $jenisKode, $tahun, $jumlahHari, $keterangan, $aktorNip) {
            CutiAlokasiTahunan::where('pegawai_nip', $nip)
                ->where('jenis_cuti_kode', $jenisKode)
                ->where('tahun_hak', $tahun)
                ->lockForUpdate()
                ->firstOrFail();

            CutiSaldoLedger::create([
                'pegawai_nip' => $nip,
                'jenis_cuti_kode' => $jenisKode,
                'tahun_hak' => $tahun,
                'jenis_transaksi' => 'penyesuaian',
                'jumlah_hari' => $jumlahHari,
                'aktor_pegawai_nip' => $aktorNip,
                'keterangan' => $keterangan,
            ]);
        });
    }

    /**
     * Menghitung jumlah hari yang bisa di-refund berdasarkan sisa hari cuti.
     */
    private function hitungRefund(CutiPengajuan $p): int
    {
        $today = Carbon::today();
        $tanggalMulai = Carbon::parse($p->tanggal_mulai)->startOfDay();
        $tanggalSelesai = Carbon::parse($p->tanggal_selesai)->startOfDay();

        // Jika belum mulai cuti, refund seluruhnya
        if ($today->lt($tanggalMulai)) {
            return $p->jumlah_hari_kerja;
        }

        // Jika cuti sudah selesai, tidak ada refund
        if ($today->gt($tanggalSelesai)) {
            return 0;
        }

        // Hitung sisa hari kerja dari besok sampai tanggal selesai
        return app(HariKerjaCalculatorService::class)->hitung(
            $today->copy()->addDay(),
            $tanggalSelesai
        );
    }
}
