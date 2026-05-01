<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\SubmitPengajuanRequest;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanLampiran;
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

        // Ambil saldo per jenis cuti yang saldo_driven
        $saldoData = [];
        foreach ($jenisCutiList->where('saldo_driven', true) as $jenis) {
            $saldoData[$jenis->kode] = $this->saldoService->saldoBucket(
                $user->nip,
                $jenis->kode,
                $tahun
            );
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
        $user = $request->user();

        $pengajuan = $this->pengajuanService->submit([
            'pegawai_nip' => $user->nip,
            'jenis_cuti_kode' => $request->validated('jenis_cuti_kode'),
            'tanggal_mulai' => $request->validated('tanggal_mulai'),
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'alasan' => $request->validated('alasan'),
            'alamat_selama_cuti' => $request->validated('alamat_selama_cuti'),
            'nomor_telp_selama_cuti' => $request->validated('nomor_telp_selama_cuti'),
        ]);

        // Simpan lampiran jika ada
        if ($request->hasFile('lampiran')) {
            foreach ($request->file('lampiran') as $file) {
                $path = $file->store("cuti/lampiran/{$pengajuan->id}", 'local');

                CutiPengajuanLampiran::create([
                    'pengajuan_id' => $pengajuan->id,
                    'jenis_lampiran' => 'pendukung',
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'path_file' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by_nip' => $user->nip,
                ]);
            }
        }

        return to_route('cuti.pengajuan.show', $pengajuan->id)
            ->with('success', 'Pengajuan cuti berhasil diajukan.');
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
            'stateHistory',
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

    /**
     * Menampilkan daftar pengajuan cuti milik user yang sedang login.
     */
    public function myPage(Request $request): Response
    {
        $user = $request->user();
        $tahun = now()->year;

        $pengajuanList = CutiPengajuan::where('pegawai_nip', $user->nip)
            ->with('jenisCuti:kode,nama')
            ->latest('submitted_at')
            ->paginate(10);

        // Ringkasan saldo CT
        $saldoCt = $this->saldoService->saldoBucket($user->nip, 'CT', $tahun);

        return Inertia::render('cuti/pengajuan/my-page', [
            'pengajuanList' => $pengajuanList,
            'saldoSummary' => [
                'CT' => $saldoCt,
                'tahun' => $tahun,
            ],
        ]);
    }
}
