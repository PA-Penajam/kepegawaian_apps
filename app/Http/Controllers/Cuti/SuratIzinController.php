<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\ResubmitSuratIzinRequest;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanPdf;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\SuratDinasRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuratIzinController extends Controller
{
    /**
     * Memperbaiki metadata Surat Izin Cuti dan mengajukannya ulang ke SiSukma.
     */
    public function resubmit(
        ResubmitSuratIzinRequest $request,
        string $id,
        SuratDinasRequestService $service,
    ): RedirectResponse {
        $pengajuan = CutiPengajuan::query()->with('suratIzinRequest')->findOrFail($id);
        $surat = $pengajuan->suratIzinRequest;

        if ($surat === null) {
            abort(404, 'Permintaan surat izin cuti belum dibuat.');
        }

        try {
            $service->resubmit($surat, $request->validated());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Surat Izin Cuti berhasil diajukan ulang ke SiSukma.');
    }

    /**
     * Mengunduh PDF draf Surat Izin Cuti bernomor yang dirender lokal.
     */
    public function downloadDraft(string $id): BinaryFileResponse
    {
        $pengajuan = CutiPengajuan::query()->findOrFail($id);
        $this->authorizeViewPengajuan($pengajuan);

        $pdf = CutiPengajuanPdf::query()
            ->where('pengajuan_id', $pengajuan->id)
            ->latest('generated_at')
            ->first();

        if ($pdf === null || ! Storage::disk('local')->exists($pdf->path_file)) {
            abort(404, 'Draf Surat Izin Cuti bernomor belum tersedia.');
        }

        $safeNomor = str_replace(['/', '\\'], '-', $pengajuan->nomor_pengajuan);

        return ResponseFactory::download(
            Storage::disk('local')->path($pdf->path_file),
            "draf-surat-izin-{$safeNomor}.pdf",
        );
    }

    /**
     * Men-stream berkas final Surat Izin Cuti bertanda tangan dari SiSukma melalui proxy.
     */
    public function streamFinal(string $id, PenomoranSuratGateway $gateway): StreamedResponse
    {
        $pengajuan = CutiPengajuan::query()->with('suratIzinRequest')->findOrFail($id);
        $this->authorizeViewPengajuan($pengajuan);

        $surat = $pengajuan->suratIzinRequest;
        if ($surat === null || ! $surat->status->hasFinalFile()) {
            abort(404, 'Surat Izin Cuti final belum diunggah di SiSukma.');
        }

        $safeNomor = str_replace(['/', '\\'], '-', $surat->nomor_surat ?? $pengajuan->nomor_pengajuan);

        return $gateway->streamFinal($surat, "surat-izin-cuti-{$safeNomor}.pdf");
    }

    /**
     * Memastikan pengguna memiliki hak lihat atas pengajuan cuti terkait.
     */
    private function authorizeViewPengajuan(CutiPengajuan $pengajuan): void
    {
        if (Gate::denies('viewOwn', $pengajuan)
            && Gate::denies('viewTeam', $pengajuan)
            && Gate::denies('viewAll', $pengajuan)
        ) {
            abort(403);
        }
    }
}
