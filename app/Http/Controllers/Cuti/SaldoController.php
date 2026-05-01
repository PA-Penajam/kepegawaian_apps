<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaldoController extends Controller
{
    public function __construct(
        private SaldoLedgerService $saldoService,
    ) {}

    /**
     * Menampilkan dashboard saldo cuti dan riwayat pengajuan untuk user yang login.
     */
    public function myDashboard(Request $request): Response
    {
        $user = $request->user();
        $tahun = now()->year;

        $saldoCt = $this->saldoService->saldoBucket($user->nip, 'CT', $tahun);

        // Ambil hak_awal dari tabel alokasi tahunan untuk bucket CT tahun ini
        $alokasi = CutiAlokasiTahunan::where('pegawai_nip', $user->nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', $tahun)
            ->first();

        $pengajuanList = CutiPengajuan::where('pegawai_nip', $user->nip)
            ->with('jenisCuti:kode,nama')
            ->latest('submitted_at')
            ->paginate(10);

        return Inertia::render('cuti/saldo/my-dashboard', [
            'saldo' => [
                'CT' => $saldoCt,
                'tahun' => $tahun,
                'hak_awal' => $alokasi?->hak_awal ?? 0,
            ],
            'pengajuanList' => $pengajuanList,
        ]);
    }

    /**
     * Menampilkan daftar saldo cuti seluruh pegawai (admin view).
     */
    public function adminIndex(Request $request): Response
    {
        $tahun = $request->input('tahun', now()->year);

        $alokasiList = CutiAlokasiTahunan::with('pegawai:id,nip,nama_lengkap')
            ->where('tahun_hak', $tahun)
            ->paginate(20)
            ->through(function (CutiAlokasiTahunan $alokasi) {
                $saldo = $this->saldoService->saldoBucket(
                    $alokasi->pegawai_nip,
                    $alokasi->jenis_cuti_kode,
                    $alokasi->tahun_hak
                );

                return [
                    'id' => $alokasi->id,
                    'pegawai_nip' => $alokasi->pegawai_nip,
                    'pegawai' => $alokasi->pegawai,
                    'jenis_cuti_kode' => $alokasi->jenis_cuti_kode,
                    'tahun_hak' => $alokasi->tahun_hak,
                    'hak_awal' => $alokasi->hak_awal,
                    'saldo_saat_ini' => $saldo,
                ];
            });

        return Inertia::render('cuti/saldo/admin-index', [
            'alokasiList' => $alokasiList,
            'tahun' => (int) $tahun,
        ]);
    }

    /**
     * Menampilkan form inisialisasi saldo cuti.
     */
    public function adminInit(): Response
    {
        return Inertia::render('cuti/saldo/admin-init', [
            'tahun' => now()->year,
        ]);
    }

    /**
     * Menyimpan inisialisasi saldo cuti via SaldoLedgerService::kreditAlokasi.
     */
    public function adminInitStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pegawai_nip' => ['required', 'string', 'exists:pegawai,nip'],
            'jenis_cuti_kode' => ['required', 'string'],
            'tahun' => ['required', 'integer', 'min:2020'],
            'jumlah_hari' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'pegawai_nip.required' => 'NIP pegawai wajib diisi.',
            'pegawai_nip.exists' => 'NIP pegawai tidak ditemukan.',
            'jumlah_hari.required' => 'Jumlah hari wajib diisi.',
            'jumlah_hari.min' => 'Jumlah hari minimal 1.',
        ]);

        $this->saldoService->kreditAlokasi(
            $validated['pegawai_nip'],
            $validated['jenis_cuti_kode'],
            (int) $validated['tahun'],
            (int) $validated['jumlah_hari'],
            $validated['keterangan'] ?? 'Inisialisasi saldo cuti'
        );

        return to_route('admin.cuti.saldo.index')
            ->with('success', 'Saldo cuti berhasil diinisialisasi.');
    }

    /**
     * Melakukan penyesuaian saldo cuti oleh admin.
     */
    public function adminAdjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pegawai_nip' => ['required', 'string', 'exists:pegawai,nip'],
            'jenis_cuti_kode' => ['required', 'string'],
            'tahun' => ['required', 'integer', 'min:2020'],
            'jumlah_hari' => ['required', 'integer'],
            'keterangan' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'pegawai_nip.required' => 'NIP pegawai wajib diisi.',
            'pegawai_nip.exists' => 'NIP pegawai tidak ditemukan.',
            'jumlah_hari.required' => 'Jumlah hari wajib diisi.',
            'keterangan.required' => 'Keterangan penyesuaian wajib diisi.',
            'keterangan.min' => 'Keterangan minimal 10 karakter.',
        ]);

        $this->saldoService->penyesuaian(
            $validated['pegawai_nip'],
            $validated['jenis_cuti_kode'],
            (int) $validated['tahun'],
            (int) $validated['jumlah_hari'],
            $validated['keterangan'],
            $request->user()->nip
        );

        return to_route('admin.cuti.saldo.index')
            ->with('success', 'Penyesuaian saldo berhasil disimpan.');
    }
}
