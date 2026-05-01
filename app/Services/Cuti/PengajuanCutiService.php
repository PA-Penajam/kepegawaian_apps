<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\AlokasiTidakAdaException;
use App\Exceptions\Cuti\CrossYearLeaveException;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\Rules\CutiRuleEngine;
use App\States\Cuti\DiajukanState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PengajuanCutiService
{
    public function __construct(
        private HariKerjaCalculatorService $hariKerjaService,
        private SaldoLedgerService $saldoService,
        private ApproverResolverService $approverResolver,
        private CutiRuleEngine $ruleEngine,
    ) {}

    /**
     * Submit pengajuan cuti baru.
     *
     * @param  array{pegawai_nip: string, jenis_cuti_kode: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string, alamat_selama_cuti?: string, nomor_telp_selama_cuti?: string}  $data
     *
     * @throws CrossYearLeaveException
     * @throws AlokasiTidakAdaException
     * @throws OverlapPengajuanException
     */
    public function submit(array $data): CutiPengajuan
    {
        $start = Carbon::parse($data['tanggal_mulai']);
        $end = Carbon::parse($data['tanggal_selesai']);

        // Validasi cross-year
        if ($start->year !== $end->year) {
            throw new CrossYearLeaveException('Pengajuan cuti tidak boleh lintas tahun.');
        }

        return DB::transaction(function () use ($data, $start, $end): CutiPengajuan {
            $tahun = $start->year;

            // Lock anchor alokasi jika CT (saldo_driven)
            if ($data['jenis_cuti_kode'] === 'CT') {
                $alokasi = CutiAlokasiTahunan::where('pegawai_nip', $data['pegawai_nip'])
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', $tahun)
                    ->lockForUpdate()
                    ->first();

                if (! $alokasi) {
                    throw new AlokasiTidakAdaException("Alokasi CT tahun {$tahun} belum diinisialisasi.");
                }
            }

            // Overlap check terhadap 4 state aktif
            $this->guardOverlap($data['pegawai_nip'], $data['tanggal_mulai'], $data['tanggal_selesai']);

            // Hitung hari kerja
            $hariKerja = $this->hariKerjaService->hitung($start, $end);

            // Resolve approver snapshot
            $approver = $this->approverResolver->resolveSnapshot($data['pegawai_nip']);

            // Buat pengajuan dalam state DRAFT
            $pengajuan = CutiPengajuan::create([
                'nomor_pengajuan' => $this->generateNomor($tahun, $data['pegawai_nip']),
                'pegawai_nip' => $data['pegawai_nip'],
                'jenis_cuti_kode' => $data['jenis_cuti_kode'],
                'tanggal_mulai' => $start,
                'tanggal_selesai' => $end,
                'jumlah_hari_kerja' => $hariKerja,
                'alasan' => $data['alasan'],
                'alamat_selama_cuti' => $data['alamat_selama_cuti'] ?? null,
                'nomor_telp_selama_cuti' => $data['nomor_telp_selama_cuti'] ?? null,
                'state' => 'DRAFT',
                'petugas_kepegawaian_snapshot_nip' => $approver['petugas_kepegawaian'],
                'atasan_langsung_snapshot_nip' => $approver['atasan_langsung'],
                'pejabat_berwenang_snapshot_nip' => $approver['pejabat_berwenang'],
                'petugas_kepegawaian_current_nip' => $approver['petugas_kepegawaian'],
                'atasan_langsung_current_nip' => $approver['atasan_langsung'],
                'pejabat_berwenang_current_nip' => $approver['pejabat_berwenang'],
                'submitted_at' => now(),
            ]);

            // Validasi per jenis cuti (Rule Engine)
            $this->ruleEngine->validate($pengajuan);

            // Transisi DRAFT → DIAJUKAN
            $pengajuan->state->transitionTo(DiajukanState::class);

            // CT only: debit_pending FIFO
            if ($data['jenis_cuti_kode'] === 'CT') {
                $this->saldoService->debitPendingFifo($pengajuan);
            }

            return $pengajuan->fresh();
        });
    }

    /**
     * Generate nomor pengajuan dengan format CUTI/YYYY/NIP-pendek/counter.
     */
    public function generateNomor(int $tahun, string $nip): string
    {
        // Ambil 8 digit terakhir NIP sebagai identifier pendek
        $nipPendek = substr($nip, -8);

        // Counter berdasarkan jumlah pengajuan yang sudah ada
        $count = CutiPengajuan::where('pegawai_nip', $nip)
            ->whereYear('created_at', $tahun)
            ->count() + 1;

        return sprintf('CUTI/%d/%s/%04d', $tahun, $nipPendek, $count);
    }

    /**
     * Cek overlap tanggal terhadap pengajuan aktif milik pegawai.
     *
     * @throws OverlapPengajuanException
     */
    private function guardOverlap(string $nip, string $mulai, string $selesai): void
    {
        $overlap = CutiPengajuan::where('pegawai_nip', $nip)
            ->whereIn('state', ['DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN', 'DISETUJUI'])
            ->where(function ($q) use ($mulai, $selesai) {
                $q->whereBetween('tanggal_mulai', [$mulai, $selesai])
                    ->orWhereBetween('tanggal_selesai', [$mulai, $selesai])
                    ->orWhere(function ($q2) use ($mulai, $selesai) {
                        $q2->where('tanggal_mulai', '<=', $mulai)
                            ->where('tanggal_selesai', '>=', $selesai);
                    });
            })
            ->exists();

        if ($overlap) {
            throw new OverlapPengajuanException('Tanggal pengajuan overlap dengan pengajuan aktif.');
        }
    }
}
