<?php

namespace App\Http\Controllers\Cuti;

use App\Exceptions\Cuti\AlokasiTidakAdaException;
use App\Exceptions\Cuti\CrossYearLeaveException;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Exceptions\Cuti\SubmitTerlambatException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\SubmitPengajuanRequest;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\Cuti\SaldoLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PengajuanController extends Controller
{
    public function __construct(
        private PengajuanCutiService $pengajuanService,
        private SaldoLedgerService $saldoService,
    ) {}

    /**
     * Menampilkan form pengajuan cuti baru.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $tahun = now()->year;

        $jenisCutiList = CutiJenisMaster::where('aktif', true)
            ->orderBy('kode')
            ->get(['kode', 'nama', 'saldo_driven', 'butuh_lampiran']);

        // Ambil saldo seluruh jenis cuti saldo_driven dalam satu query agregat (anti N+1)
        $saldoDrivenJenis = $jenisCutiList->where('saldo_driven', true);
        $buckets = $saldoDrivenJenis->map(fn (CutiJenisMaster $jenis): array => [
            'nip' => $user->nip,
            'jenis' => $jenis->kode,
            'tahun' => $tahun,
        ])->values()->all();
        $saldos = $this->saldoService->saldoBuckets($buckets);

        $saldoData = [];
        foreach ($saldoDrivenJenis as $jenis) {
            $saldoData[$jenis->kode] = $saldos["{$user->nip}|{$jenis->kode}|{$tahun}"] ?? 0;
        }

        return Inertia::render('cuti/pengajuan/create', [
            'jenisCutiList' => $jenisCutiList,
            'saldoData' => $saldoData,
        ]);
    }

    /**
     * Menyimpan pengajuan cuti baru.
     */
    public function store(SubmitPengajuanRequest $request): RedirectResponse
    {
        try {
            $user = $request->user();

            $lampiranFiles = $request->file('lampiran', []);
            if (! is_array($lampiranFiles)) {
                $lampiranFiles = [$lampiranFiles];
            }

            $pengajuan = $this->pengajuanService->submit([
                'pegawai_nip' => $user->nip,
                'jenis_cuti_kode' => $request->validated('jenis_cuti_kode'),
                'tanggal_mulai' => $request->validated('tanggal_mulai'),
                'tanggal_selesai' => $request->validated('tanggal_selesai'),
                'alasan' => $request->validated('alasan'),
                'alamat_selama_cuti' => $request->validated('alamat_selama_cuti'),
                'nomor_telp_selama_cuti' => $request->validated('nomor_telp_selama_cuti'),
            ], $lampiranFiles);

            return to_route('cuti.pengajuan.show', $pengajuan->id)
                ->with('success', 'Pengajuan cuti berhasil diajukan.');
        } catch (
            CrossYearLeaveException
            |AlokasiTidakAdaException
            |OverlapPengajuanException
            |SubmitTerlambatException
            |SaldoTidakCukupException
            |\DomainException $e
        ) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            report($e);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan pengajuan cuti. Silakan coba lagi.');
        }
    }

    /**
     * Menampilkan detail pengajuan cuti.
     */
    public function show(Request $request, string $id): Response
    {
        $pengajuan = CutiPengajuan::with([
            'pegawai:id,nip,nama_lengkap',
            'jenisCuti',
            'lampiran',
            'approvalSteps',
            'stateHistory.aktor:id,nip,nama_lengkap',
            'approverHistory',
            'atasanLangsungCurrent:id,nip,nama_lengkap',
            'pejabatBerwenangCurrent:id,nip,nama_lengkap',
            'petugasKepegawaianCurrent:id,nip,nama_lengkap',
        ])->findOrFail($id);

        // Cek otorisasi: pemilik, tim (atasan/pejabat), atau admin
        if (Gate::denies('viewOwn', $pengajuan)
            && Gate::denies('viewTeam', $pengajuan)
            && Gate::denies('viewAll', $pengajuan)
        ) {
            abort(403);
        }

        return Inertia::render('cuti/pengajuan/show', [
            'pengajuan' => $pengajuan,
        ]);
    }
}
