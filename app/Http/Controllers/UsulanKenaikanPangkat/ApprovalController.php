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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

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
        $approvalContexts = $this->approvalContexts($user);

        $query = UsulanKenaikanPangkat::query()
            ->with(['pegawai:id,nip,nama_lengkap', 'pangkatAsal:id,kode,nama', 'pangkatTujuan:id,kode,nama'])
            ->latest('submitted_at');

        if ($approvalContexts === []) {
            $query->whereRaw('1 = 0');
        } else {
            $states = array_column($approvalContexts, 'state');
            $query->where(function ($subQuery) use ($states): void {
                foreach ($states as $index => $state) {
                    if ($index === 0) {
                        $subQuery->where('state', $state);
                    } else {
                        $subQuery->orWhere('state', $state);
                    }
                }
            });
        }

        return Inertia::render('kenaikan-pangkat/approval/inbox', [
            'usulan' => $query->paginate(10)->withQueryString(),
            'current_role' => $approvalContexts[0]['role'] ?? null,
        ]);
    }

    public function verifikasiKasubbag(VerifikasiKasubbagRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('verifikasiKasubbag', $usulan);

        try {
            $this->service->verifikasiKasubbag(
                $usulan,
                $request->user(),
                $request->boolean('setuju'),
                $request->validated('catatan')
            );
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat diverifikasi kasubbag.',
            ]);
        }

        return back()->with('success', 'Usulan berhasil diverifikasi kasubbag.');
    }

    public function verifikasiSekretaris(VerifikasiSekretarisRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('verifikasiSekretaris', $usulan);

        try {
            $this->service->verifikasiSekretaris(
                $usulan,
                $request->user(),
                $request->boolean('setuju'),
                $request->validated('catatan')
            );
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat diverifikasi sekretaris.',
            ]);
        }

        return back()->with('success', 'Usulan berhasil diverifikasi sekretaris.');
    }

    public function tandaTanganKetua(TandaTanganKetuaRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('tandaTanganKetua', $usulan);

        try {
            $this->service->tandaTanganKetua($usulan, $request->user());
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat ditandatangani ulang.',
            ]);
        }

        return back()->with('success', 'Surat pengantar berhasil ditandatangani ketua.');
    }

    public function kirimBiro(KirimBiroRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('kirimBiro', $usulan);

        try {
            $this->service->kirimBiro($usulan, $request->user(), $request->validated('catatan'));
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat dikirim ke Biro Kepegawaian.',
            ]);
        }

        return back()->with('success', 'Usulan berhasil dikirim ke Biro Kepegawaian.');
    }

    public function mintaPerbaikan(MintaPerbaikanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('mintaPerbaikan', $usulan);

        try {
            $this->service->mintaPerbaikan($usulan, $request->user(), $request->validated('catatan'));
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat diminta perbaikan.',
            ]);
        }

        return back()->with('success', 'Permintaan perbaikan berhasil dikirim.');
    }

    public function tolak(TolakUsulanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('tolak', $usulan);

        try {
            $this->service->tolak($usulan, $request->user(), $request->validated('alasan'));
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat ditolak.',
            ]);
        }

        return back()->with('success', 'Usulan berhasil ditolak.');
    }

    /**
     * Mengumpulkan seluruh konteks role/state approval yang dimiliki user.
     *
     * @return array<int, array{role: string, state: string}>
     */
    private function approvalContexts(Pegawai $user): array
    {
        $mappings = [
            'kenaikan-pangkat.usulan.verifikasi-kasubbag' => ['role' => 'kasubbag', 'state' => 'DIAJUKAN'],
            'kenaikan-pangkat.usulan.verifikasi-sekretaris' => ['role' => 'sekretaris', 'state' => 'DIVERIFIKASI_KASUBBAG'],
            'kenaikan-pangkat.usulan.tanda-tangan-ketua' => ['role' => 'ketua', 'state' => 'DIVERIFIKASI_SEKRETARIS'],
            'kenaikan-pangkat.usulan.kirim-biro' => ['role' => 'biro', 'state' => 'DITANDATANGANI_KETUA'],
        ];

        $contexts = [];

        foreach ($mappings as $permission => $context) {
            if ($user->hasPermission($permission)) {
                $contexts[] = $context;
            }
        }

        return $contexts;
    }
}
