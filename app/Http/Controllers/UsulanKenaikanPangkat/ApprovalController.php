<?php

namespace App\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsulanKenaikanPangkat\KirimBiroRequest;
use App\Http\Requests\UsulanKenaikanPangkat\MintaPerbaikanRequest;
use App\Http\Requests\UsulanKenaikanPangkat\TandaTanganKetuaRequest;
use App\Http\Requests\UsulanKenaikanPangkat\TolakUsulanRequest;
use App\Http\Requests\UsulanKenaikanPangkat\VerifikasiKasubbagRequest;
use App\Http\Requests\UsulanKenaikanPangkat\VerifikasiSekretarisRequest;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly UsulanKenaikanPangkatService $service,
    ) {}

    /**
     * Menampilkan daftar usulan kenaikan pangkat yang menunggu aksi user.
     */
    public function inbox(Request $request): Response
    {
        /** @var Pegawai $user */
        $user = $request->user();
        $approvalContext = $this->approvalContext($user);

        $query = UsulanKenaikanPangkat::query()
            ->with(['pegawai:id,nip,nama_lengkap', 'pangkatAsal:id,kode,nama', 'pangkatTujuan:id,kode,nama'])
            ->latest('submitted_at');

        if ($approvalContext === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('state', $approvalContext['state']);
        }

        return Inertia::render('kenaikan-pangkat/approval/inbox', [
            'usulan' => $query->paginate(10)->withQueryString(),
            'current_role' => $approvalContext['role'] ?? null,
        ]);
    }

    public function verifikasiKasubbag(VerifikasiKasubbagRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('verifikasiKasubbag', $usulan);

        $this->service->verifikasiKasubbag(
            $usulan,
            $request->user(),
            $request->boolean('setuju'),
            $request->validated('catatan')
        );

        return back()->with('success', 'Usulan berhasil diverifikasi kasubbag.');
    }

    public function verifikasiSekretaris(VerifikasiSekretarisRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('verifikasiSekretaris', $usulan);

        $this->service->verifikasiSekretaris(
            $usulan,
            $request->user(),
            $request->boolean('setuju'),
            $request->validated('catatan')
        );

        return back()->with('success', 'Usulan berhasil diverifikasi sekretaris.');
    }

    public function tandaTanganKetua(TandaTanganKetuaRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('tandaTanganKetua', $usulan);

        $this->service->tandaTanganKetua($usulan, $request->user());

        return back()->with('success', 'Surat pengantar berhasil ditandatangani ketua.');
    }

    public function kirimBiro(KirimBiroRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('kirimBiro', $usulan);

        $this->service->kirimBiro($usulan, $request->user(), $request->validated('catatan'));

        return back()->with('success', 'Usulan berhasil dikirim ke Biro Kepegawaian.');
    }

    public function mintaPerbaikan(MintaPerbaikanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('view', $usulan);

        $this->service->mintaPerbaikan($usulan, $request->user(), $request->validated('catatan'));

        return back()->with('success', 'Permintaan perbaikan berhasil dikirim.');
    }

    public function tolak(TolakUsulanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('view', $usulan);

        $this->service->tolak($usulan, $request->user(), $request->validated('alasan'));

        return back()->with('success', 'Usulan berhasil ditolak.');
    }

    /**
     * @return array{role: string, state: string}|null
     */
    private function approvalContext(Pegawai $user): ?array
    {
        return match (true) {
            $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-kasubbag') => ['role' => 'kasubbag', 'state' => 'DIAJUKAN'],
            $user->hasPermission('kenaikan-pangkat.usulan.verifikasi-sekretaris') => ['role' => 'sekretaris', 'state' => 'DIVERIFIKASI_KASUBBAG'],
            $user->hasPermission('kenaikan-pangkat.usulan.tanda-tangan-ketua') => ['role' => 'ketua', 'state' => 'DIVERIFIKASI_SEKRETARIS'],
            $user->hasPermission('kenaikan-pangkat.usulan.kirim-biro') => ['role' => 'biro', 'state' => 'DITANDATANGANI_KETUA'],
            default => null,
        };
    }
}
