<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\CatatKeputusanEksternalRequest;
use App\Http\Requests\Cuti\CatatRegistrasiEksternalRequest;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\WorkflowService;
use App\Services\Sisukma\Exceptions\SisukmaException;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerizinanEksternalController extends Controller
{
    /**
     * Mencari Surat Masuk non-rahasia di SiSukma untuk ditautkan sebagai arsip SK PTA/MA.
     */
    public function searchIncomingMails(Request $request, PenomoranSuratGateway $gateway): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('cuti.pengajuan.verify') ?? false, 403);

        $query = trim((string) $request->query('q', ''));

        try {
            return response()->json([
                'data' => $gateway->searchIncomingMails($query),
            ]);
        } catch (SisukmaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => [],
            ], 502);
        }
    }

    /**
     * Mencatat nomor registrasi dan tanggal pengajuan manual di aplikasi PTA/MA.
     */
    public function catatRegistrasi(
        CatatRegistrasiEksternalRequest $request,
        string $id,
        WorkflowService $workflowService,
    ): RedirectResponse {
        try {
            $workflowService->catatRegistrasiEksternal(
                pengajuanId: $id,
                aktor: $request->user(),
                data: $request->validated(),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nomor registrasi aplikasi eksternal berhasil dicatat.');
    }

    /**
     * Mencatat keputusan akhir PTA/MA beserta tautan SK Surat Masuk di SiSukma.
     */
    public function catatKeputusan(
        CatatKeputusanEksternalRequest $request,
        string $id,
        WorkflowService $workflowService,
    ): RedirectResponse {
        try {
            $workflowService->catatKeputusanEksternal(
                pengajuanId: $id,
                aktor: $request->user(),
                data: $request->validated(),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Keputusan instansi atasan dan tautan SK SiSukma berhasil disimpan.');
    }

    /**
     * Men-stream berkas scan SK dari Surat Masuk SiSukma melalui proxy terotorisasi KA.
     */
    public function streamSk(string $id, PenomoranSuratGateway $gateway): StreamedResponse
    {
        $pengajuan = CutiPengajuan::query()->with('perizinanEksternal')->findOrFail($id);

        if (Gate::denies('viewOwn', $pengajuan)
            && Gate::denies('viewTeam', $pengajuan)
            && Gate::denies('viewAll', $pengajuan)
        ) {
            abort(403);
        }

        $publicId = $pengajuan->perizinanEksternal?->sisukma_incoming_mail_public_id;
        if (blank($publicId)) {
            abort(404, 'Berkas SK instansi atasan belum ditautkan ke Surat Masuk SiSukma.');
        }

        $safeNomor = str_replace(['/', '\\'], '-', $pengajuan->perizinanEksternal?->nomor_sk ?? $pengajuan->nomor_pengajuan);

        return $gateway->streamIncomingMailFile((string) $publicId, "sk-cuti-{$safeNomor}.pdf");
    }
}
