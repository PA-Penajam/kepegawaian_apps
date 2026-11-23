<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\AlokasiTidakAdaException;
use App\Exceptions\Cuti\CrossYearLeaveException;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanLampiran;
use App\Models\Pegawai;
use App\Services\Cuti\Rules\CutiRuleEngine;
use App\States\Cuti\DiajukanState;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PengajuanCutiService
{
    public function __construct(
        private HariKerjaCalculatorService $hariKerjaService,
        private SaldoLedgerService $saldoService,
        private ApproverResolverService $approverResolver,
        private CutiRuleEngine $ruleEngine,
    ) {}

    /**
     * Submit pengajuan cuti baru beserta penyimpanan lampiran secara atomik.
     *
     * @param  array{pegawai_nip: string, jenis_cuti_kode: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string, alamat_selama_cuti?: string, nomor_telp_selama_cuti?: string}  $data
     * @param  array<int, UploadedFile>  $lampiranFiles
     *
     * @throws CrossYearLeaveException
     * @throws AlokasiTidakAdaException
     * @throws OverlapPengajuanException
     */
    public function submit(array $data, array $lampiranFiles = []): CutiPengajuan
    {
        $start = Carbon::parse($data['tanggal_mulai']);
        $end = Carbon::parse($data['tanggal_selesai']);

        // Validasi cross-year
        if ($start->year !== $end->year) {
            throw new CrossYearLeaveException('Pengajuan cuti tidak boleh lintas tahun.');
        }

        $jenisCuti = CutiJenisMaster::where('kode', $data['jenis_cuti_kode'])
            ->where('aktif', true)
            ->first();

        if (! $jenisCuti) {
            throw new \DomainException('Jenis cuti tidak ditemukan atau tidak aktif.');
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $start, $end, $jenisCuti, $lampiranFiles, &$storedPaths): CutiPengajuan {
                $tahun = $start->year;

                // Lock baris pegawai untuk men-serialisasi submit concurrent per pegawai,
                // sehingga generateNomor (count+1) dan guardOverlap aman dari race condition
                $pegawai = Pegawai::where('nip', $data['pegawai_nip'])->lockForUpdate()->firstOrFail();

                // Lock seluruh bucket alokasi tahun_hak <= $tahun jika jenis cuti berbasis saldo
                if ($jenisCuti->saldo_driven) {
                    $alokasiBuckets = CutiAlokasiTahunan::where('pegawai_nip', $data['pegawai_nip'])
                        ->where('jenis_cuti_kode', $jenisCuti->kode)
                        ->where('tahun_hak', '<=', $tahun)
                        ->orderBy('tahun_hak', 'asc')
                        ->lockForUpdate()
                        ->get();

                    if (! $alokasiBuckets->contains('tahun_hak', $tahun)) {
                        throw new AlokasiTidakAdaException("Alokasi {$jenisCuti->kode} tahun {$tahun} belum diinisialisasi.");
                    }
                }

                // Overlap check terhadap 4 state aktif
                $this->guardOverlap($data['pegawai_nip'], $data['tanggal_mulai'], $data['tanggal_selesai']);

                // Hitung hari kerja dan pastikan minimal 1 hari kerja
                $hariKerja = $this->hariKerjaService->hitung($start, $end);
                if ($hariKerja < 1) {
                    throw new \DomainException('Pengajuan cuti harus mencakup minimal 1 hari kerja.');
                }

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

                // Pasang relasi pegawai agar rule engine bebas lazy-loading violation
                $pengajuan->setRelation('pegawai', $pegawai);
                $pengajuan->setRelation('jenisCuti', $jenisCuti);

                // Simpan lampiran sebelum rule engine berjalan agar rule wajib lampiran tervalidasi
                foreach ($lampiranFiles as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->store("cuti/lampiran/{$pengajuan->id}", 'local');
                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('Gagal menyimpan berkas lampiran.');
                    }

                    $storedPaths[] = $path;

                    CutiPengajuanLampiran::create([
                        'pengajuan_id' => $pengajuan->id,
                        'jenis_lampiran' => 'pendukung',
                        'nama_file_asli' => $file->getClientOriginalName(),
                        'path_file' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                        'uploaded_by_nip' => $data['pegawai_nip'],
                    ]);
                }

                // Validasi per jenis cuti (Rule Engine)
                $this->ruleEngine->validate($pengajuan);

                // Transisi DRAFT → DIAJUKAN
                $pengajuan->state->transitionTo(DiajukanState::class);

                // Jenis cuti saldo-driven: debit_pending FIFO
                if ($jenisCuti->saldo_driven) {
                    $this->saldoService->debitPendingFifo($pengajuan);
                }

                return $pengajuan->fresh();
            });
        } catch (\Throwable $e) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $e;
        }
    }

    /**
     * Generate nomor pengajuan dengan format CUTI/YYYY/NIP-pendek/counter.
     *
     * Catatan konkurensi: counter berbasis count+1 berdasarkan tahun mulai cuti,
     * sehingga pemanggilan untuk keperluan penyimpanan wajib berada dalam
     * transaksi dengan lock baris pegawai (lihat submit()).
     */
    public function generateNomor(int $tahun, string $nip): string
    {
        // Ambil 8 digit terakhir NIP sebagai identifier pendek
        $nipPendek = substr($nip, -8);

        // Counter berdasarkan tahun tanggal_mulai cuti agar lintas akhir tahun tidak bentrok
        $count = CutiPengajuan::where('pegawai_nip', $nip)
            ->whereYear('tanggal_mulai', $tahun)
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
