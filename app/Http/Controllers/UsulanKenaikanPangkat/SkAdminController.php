<?php

namespace App\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsulanKenaikanPangkat\UploadSkFinalRequest;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkAdminController extends Controller
{
    public function __construct(
        private readonly UsulanKenaikanPangkatService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UsulanKenaikanPangkat::class);

        $rawState = $request->input('state');
        $stateFilter = is_string($rawState) ? $rawState : '';
        $allowedStates = ['MENUNGGU_SK', 'SELESAI_SK_TERBIT'];
        $selectedState = in_array($stateFilter, $allowedStates, true) ? $stateFilter : null;

        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        $usulan = UsulanKenaikanPangkat::query()
            ->with(['pegawai', 'pangkatAsal', 'pangkatTujuan'])
            ->when(
                $selectedState !== null,
                fn ($query) => $query->where('state', $selectedState),
                fn ($query) => $query->whereIn('state', $allowedStates),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('kenaikan-pangkat/admin-sk/index', [
            'usulan' => $usulan,
            'filters' => [
                'state' => $selectedState ?? 'all',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function uploadSk(UploadSkFinalRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('uploadSk', $usulan);

        /** @var Pegawai $actor */
        $actor = $request->user();

        try {
            $this->service->uploadSkFinal(
                $usulan,
                $actor,
                $request->file('sk_file'),
                $request->string('nomor_sk')->toString(),
                $request->date('tanggal_sk')->toDateString(),
                $request->date('tmt_pangkat_baru')?->toDateString(),
                $request->filled('masa_kerja_tahun') ? $request->integer('masa_kerja_tahun') : null,
                $request->filled('masa_kerja_bulan') ? $request->integer('masa_kerja_bulan') : null,
            );
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat mengunggah SK final.',
            ]);
        }

        return back()->with('success', 'SK kenaikan pangkat berhasil diunggah.');
    }

    public function downloadSk(UsulanKenaikanPangkat $usulan): BinaryFileResponse
    {
        $this->authorize('view', $usulan);

        if (blank($usulan->sk_file_path) || ! Storage::disk('local')->exists($usulan->sk_file_path)) {
            abort(404, 'File SK kenaikan pangkat tidak ditemukan.');
        }

        return ResponseFactory::download(
            Storage::disk('local')->path($usulan->sk_file_path),
            $this->sanitizeDownloadFilename($usulan->sk_file_original_name, $usulan->id),
        );
    }

    /**
     * Membersihkan nama file unduhan dari karakter pemisah path dan null-byte.
     */
    private function sanitizeDownloadFilename(?string $originalName, string $usulanId): string
    {
        $normalized = str_replace(["\0", '\\'], ['', '/'], trim((string) $originalName));
        $base = trim(basename($normalized));

        if ($base === '' || $base === '.' || $base === '..') {
            return "sk-kenaikan-pangkat-{$usulanId}.pdf";
        }

        return $base;
    }

    public function downloadSuratPengantar(UsulanKpPdf $pdf, PenomoranSuratGateway $gateway): BinaryFileResponse|StreamedResponse
    {
        $pdf->loadMissing('usulan.suratPengantarRequest');

        if ($pdf->usulan === null) {
            abort(404, 'Usulan kenaikan pangkat tidak ditemukan.');
        }

        $this->authorize('view', $pdf->usulan);

        $suratRequest = $pdf->usulan->suratPengantarRequest;
        if ($suratRequest !== null && $suratRequest->status->hasFinalFile()) {
            return $gateway->streamFinal($suratRequest, "surat-pengantar-{$pdf->usulan_kenaikan_pangkat_id}.pdf");
        }

        if (blank($pdf->file_path) || ! Storage::disk('local')->exists($pdf->file_path)) {
            abort(404, 'File surat pengantar tidak ditemukan.');
        }

        return ResponseFactory::download(
            Storage::disk('local')->path($pdf->file_path),
            "surat-pengantar-{$pdf->usulan_kenaikan_pangkat_id}.pdf",
        );
    }
}
