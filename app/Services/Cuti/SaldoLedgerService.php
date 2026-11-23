<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiJenisMaster;
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
        return $this->saldoBuckets([['nip' => $nip, 'jenis' => $jenisKode, 'tahun' => $tahun]])["{$nip}|{$jenisKode}|{$tahun}"] ?? 0;
    }

    /**
     * Menghitung saldo banyak bucket sekaligus dalam SATU query agregat.
     *
     * Dipakai tampilan admin agar N baris alokasi tidak memicu N query SUM.
     *
     * @param  array<int, array{nip: string, jenis: string, tahun: int}>  $buckets
     * @return array<string, int> ["nip|jenis|tahun" => saldo]
     */
    public function saldoBuckets(array $buckets): array
    {
        if ($buckets === []) {
            return [];
        }

        $nips = array_unique(array_column($buckets, 'nip'));
        $jenisList = array_unique(array_column($buckets, 'jenis'));
        $tahuns = array_unique(array_column($buckets, 'tahun'));

        $rows = CutiSaldoLedger::whereIn('pegawai_nip', $nips)
            ->whereIn('jenis_cuti_kode', $jenisList)
            ->whereIn('tahun_hak', $tahuns)
            ->groupBy('pegawai_nip', 'jenis_cuti_kode', 'tahun_hak')
            ->selectRaw('pegawai_nip, jenis_cuti_kode, tahun_hak, COALESCE(SUM(jumlah_hari), 0) as saldo')
            ->get();

        $hasil = [];

        foreach ($rows as $row) {
            $hasil["{$row->pegawai_nip}|{$row->jenis_cuti_kode}|{$row->tahun_hak}"] = (int) $row->saldo;
        }

        return $hasil;
    }

    /**
     * Mengembalikan bucket alokasi tahunan yang masih memiliki saldo positif, terurut ASC.
     */
    public function bucketsAktif(string $nip, string $jenisKode, ?int $maxTahun = null): Collection
    {
        $alokasiList = CutiAlokasiTahunan::where('pegawai_nip', $nip)
            ->where('jenis_cuti_kode', $jenisKode)
            ->when($maxTahun !== null, fn ($q) => $q->where('tahun_hak', '<=', $maxTahun))
            ->orderBy('tahun_hak', 'asc')
            ->get();

        $bucketsInput = $alokasiList->map(fn (CutiAlokasiTahunan $a): array => [
            'nip' => $nip,
            'jenis' => $jenisKode,
            'tahun' => $a->tahun_hak,
        ])->all();

        $saldos = $this->saldoBuckets($bucketsInput);

        return $alokasiList
            ->filter(fn (CutiAlokasiTahunan $a): bool => ($saldos["{$nip}|{$jenisKode}|{$a->tahun_hak}"] ?? 0) > 0)
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
        $tahunMax = $p->tahunHak();

        $alokasiList = CutiAlokasiTahunan::where('pegawai_nip', $p->pegawai_nip)
            ->where('jenis_cuti_kode', $p->jenis_cuti_kode)
            ->where('tahun_hak', '<=', $tahunMax)
            ->when($p->jenis_cuti_kode === 'CT', fn ($q) => $q->where('tahun_hak', '>=', $tahunMax - 1))
            ->orderBy('tahun_hak', 'asc')
            ->lockForUpdate()
            ->get();

        $saldos = $this->saldoBuckets(
            $alokasiList->map(fn (CutiAlokasiTahunan $a): array => [
                'nip' => $p->pegawai_nip,
                'jenis' => $p->jenis_cuti_kode,
                'tahun' => $a->tahun_hak,
            ])->all()
        );

        $buckets = $alokasiList
            ->filter(fn (CutiAlokasiTahunan $a): bool => ($saldos["{$p->pegawai_nip}|{$p->jenis_cuti_kode}|{$a->tahun_hak}"] ?? 0) > 0)
            ->values();

        $sisa = $p->jumlah_hari_kerja;
        $rows = [];

        foreach ($buckets as $bucket) {
            if ($sisa <= 0) {
                break;
            }

            $available = $saldos["{$p->pegawai_nip}|{$p->jenis_cuti_kode}|{$bucket->tahun_hak}"] ?? 0;
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
     * Menjaga aturan carry-over lintas tahun: bucket <= N-2 tidak bangkit kembali dan N-1 tetap maksimal 6 hari.
     */
    public function voidPending(CutiPengajuan $p): void
    {
        $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
            ->where('jenis_transaksi', 'debit_pending')
            ->get();

        $touchedYears = [];

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

            $touchedYears[(int) $pending->tahun_hak] = true;
        }

        if ($p->jenis_cuti_kode !== 'CT' || $touchedYears === []) {
            return;
        }

        $currentYear = (int) now()->year;
        $carryOverSudahBerjalan = CutiSaldoLedger::where('pegawai_nip', $p->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where(function ($query) use ($currentYear): void {
                $query->where(function ($sub) use ($currentYear): void {
                    $sub->where('tahun_hak', $currentYear)
                        ->where('jenis_transaksi', 'kredit')
                        ->where('keterangan', 'Alokasi carry-over otomatis');
                })->orWhere(function ($sub) use ($currentYear): void {
                    $sub->where('tahun_hak', $currentYear - 1)
                        ->where('jenis_transaksi', 'expire')
                        ->where('keterangan', 'like', 'Cap carry-over%');
                })->orWhere(function ($sub) use ($currentYear): void {
                    $sub->where('tahun_hak', $currentYear - 2)
                        ->where('jenis_transaksi', 'expire')
                        ->where('keterangan', 'like', 'Hangus carry-over%');
                });
            })
            ->exists();

        foreach (array_keys($touchedYears) as $bucketTahun) {
            if ($bucketTahun <= $currentYear - 2) {
                CutiAlokasiTahunan::where('pegawai_nip', $p->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', $bucketTahun)
                    ->lockForUpdate()
                    ->first();

                $saldoBucket = $this->saldoBucket($p->pegawai_nip, 'CT', $bucketTahun);
                if ($saldoBucket > 0) {
                    CutiSaldoLedger::create([
                        'pengajuan_id' => $p->id,
                        'pegawai_nip' => $p->pegawai_nip,
                        'jenis_cuti_kode' => 'CT',
                        'tahun_hak' => $bucketTahun,
                        'jenis_transaksi' => 'expire',
                        'jumlah_hari' => -$saldoBucket,
                        'aktor_pegawai_nip' => $p->pegawai_nip,
                        'keterangan' => "Hangus otomatis pasca-void tahun {$bucketTahun}",
                    ]);
                }
            } elseif ($bucketTahun === $currentYear - 1 && ($p->tahunHak() < $currentYear || $carryOverSudahBerjalan)) {
                CutiAlokasiTahunan::where('pegawai_nip', $p->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', $bucketTahun)
                    ->lockForUpdate()
                    ->first();

                $saldoBucket = $this->saldoBucket($p->pegawai_nip, 'CT', $bucketTahun);
                $awalTahunBerjalan = sprintf('%04d-01-01', $currentYear);

                // Hitung mutasi pemakaian oleh pengajuan tahun >= N agar tidak membypass batas maksimal carry-over 6 hari.
                $mutasiTahunBerjalan = (int) CutiSaldoLedger::query()
                    ->where('pegawai_nip', $p->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', $bucketTahun)
                    ->whereIn('jenis_transaksi', ['debit_pending', 'debit_void', 'debit_confirmed', 'kredit_refund'])
                    ->whereHas('pengajuan', fn ($q) => $q->whereDate('tanggal_mulai', '>=', $awalTahunBerjalan))
                    ->sum('jumlah_hari');

                $saldoPraTahunBerjalan = $saldoBucket - $mutasiTahunBerjalan;

                if ($saldoPraTahunBerjalan > 6) {
                    $kelebihan = min($saldoBucket, $saldoPraTahunBerjalan - 6);
                    if ($kelebihan > 0) {
                        CutiSaldoLedger::create([
                            'pengajuan_id' => $p->id,
                            'pegawai_nip' => $p->pegawai_nip,
                            'jenis_cuti_kode' => 'CT',
                            'tahun_hak' => $bucketTahun,
                            'jenis_transaksi' => 'expire',
                            'jumlah_hari' => -$kelebihan,
                            'aktor_pegawai_nip' => $p->pegawai_nip,
                            'keterangan' => "Cap otomatis pasca-void tahun {$bucketTahun} max 6",
                        ]);
                    }
                }
            }
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
        if ($jumlahHari === 0) {
            throw new \InvalidArgumentException('Jumlah hari penyesuaian tidak boleh bernilai 0.');
        }

        $jenisCuti = CutiJenisMaster::where('kode', $jenisKode)
            ->where('aktif', true)
            ->first();

        if (! $jenisCuti || ! $jenisCuti->saldo_driven) {
            throw new \DomainException('Penyesuaian saldo hanya diperbolehkan untuk jenis cuti berbasis saldo yang aktif.');
        }

        DB::transaction(function () use ($nip, $jenisKode, $tahun, $jumlahHari, $keterangan, $aktorNip): void {
            CutiAlokasiTahunan::where('pegawai_nip', $nip)
                ->where('jenis_cuti_kode', $jenisKode)
                ->where('tahun_hak', $tahun)
                ->lockForUpdate()
                ->firstOrFail();

            $saldoSaatIni = $this->saldoBucket($nip, $jenisKode, $tahun);
            if ($saldoSaatIni + $jumlahHari < 0) {
                throw new SaldoTidakCukupException(
                    "Saldo tidak boleh menjadi negatif (tersedia: {$saldoSaatIni}, penyesuaian: {$jumlahHari})."
                );
            }

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
