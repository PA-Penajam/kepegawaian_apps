<?php

namespace App\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsulanKenaikanPangkat\UploadSkFinalRequest;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SkAdminController extends Controller
{
    public function __construct(
        private readonly UsulanKenaikanPangkatService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UsulanKenaikanPangkat::class);

        $usulan = UsulanKenaikanPangkat::query()
            ->with(['pegawai', 'pangkatAsal', 'pangkatTujuan'])
            ->whereIn('state', ['MENUNGGU_SK', 'SELESAI_SK_TERBIT'])
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('kenaikan-pangkat/admin-sk/index', [
            'usulan' => $usulan,
            'filters' => $request->only('per_page'),
        ]);
    }

    public function uploadSk(UploadSkFinalRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('uploadSk', $usulan);

        /** @var Pegawai $actor */
        $actor = $request->user();

        $this->service->uploadSkFinal(
            $usulan,
            $actor,
            $request->file('sk_file'),
            $request->string('nomor_sk')->toString(),
            $request->date('tanggal_sk')->toDateString(),
        );

        return back()->with('success', 'SK kenaikan pangkat berhasil diunggah.');
    }

    public function downloadSk(UsulanKenaikanPangkat $usulan): BinaryFileResponse
    {
        $this->authorize('view', $usulan);

        return ResponseFactory::download(
            Storage::disk('local')->path($usulan->sk_file_path),
            $usulan->sk_file_original_name ?: "sk-kenaikan-pangkat-{$usulan->id}.pdf",
        );
    }

    public function downloadSuratPengantar(UsulanKpPdf $pdf): BinaryFileResponse
    {
        $this->authorize('view', $pdf->usulan);

        return ResponseFactory::download(
            Storage::disk('local')->path($pdf->file_path),
            "surat-pengantar-{$pdf->usulan_kenaikan_pangkat_id}.pdf",
        );
    }
}
