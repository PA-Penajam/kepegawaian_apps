<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\ApproveRequest;
use App\Http\Requests\Cuti\ReassignApproverRequest;
use App\Http\Requests\Cuti\RejectRequest;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private WorkflowService $workflowService,
    ) {}

    /**
     * Menampilkan inbox pengajuan cuti yang menunggu tindakan user.
     */
    public function inbox(Request $request): Response
    {
        $user = $request->user();

        $query = CutiPengajuan::with(['pegawai:id,nip,nama_lengkap', 'jenisCuti:kode,nama'])
            ->latest('submitted_at');

        // Filter berdasarkan role/permission user
        if ($user->hasPermission('cuti.pengajuan.verify')) {
            $query->where(function ($q) use ($user) {
                $q->where('petugas_kepegawaian_current_nip', $user->nip)
                    ->where('state', 'DIAJUKAN');
            });
        } elseif ($user->hasPermission('cuti.pengajuan.approve-langsung')) {
            $query->where(function ($q) use ($user) {
                $q->where('atasan_langsung_current_nip', $user->nip)
                    ->where('state', 'DIVERIFIKASI');
            });
        } elseif ($user->hasPermission('cuti.pengajuan.approve-pejabat')) {
            $query->where(function ($q) use ($user) {
                $q->where('pejabat_berwenang_current_nip', $user->nip)
                    ->where('state', 'DISETUJUI_ATASAN');
            });
        } else {
            // Jika tidak punya permission approval, tampilkan inbox kosong
            $query->whereRaw('1 = 0');
        }

        return Inertia::render('cuti/approval/inbox', [
            'pengajuanList' => $query->paginate(10),
        ]);
    }

    /**
     * Verifikasi pengajuan oleh petugas kepegawaian.
     */
    public function verify(ApproveRequest $request, string $id): RedirectResponse
    {
        $this->workflowService->verify(
            $id,
            $request->user(),
            $request->validated('catatan')
        );

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Pengajuan berhasil diverifikasi.');
    }

    /**
     * Persetujuan oleh atasan langsung.
     */
    public function approveAtasan(ApproveRequest $request, string $id): RedirectResponse
    {
        $this->workflowService->approveAtasan(
            $id,
            $request->user(),
            $request->validated('catatan')
        );

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Pengajuan berhasil disetujui oleh atasan langsung.');
    }

    /**
     * Persetujuan oleh pejabat berwenang (final).
     */
    public function approvePejabat(ApproveRequest $request, string $id): RedirectResponse
    {
        $this->workflowService->approvePejabat(
            $id,
            $request->user(),
            $request->validated('catatan')
        );

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Pengajuan berhasil disetujui oleh pejabat berwenang.');
    }

    /**
     * Penolakan pengajuan cuti berdasarkan role aktor.
     */
    public function reject(RejectRequest $request, string $id): RedirectResponse
    {
        $user = $request->user();

        // Tentukan role berdasarkan permission user
        $role = match (true) {
            $user->hasPermission('cuti.pengajuan.approve-pejabat') => 'pejabat_berwenang',
            $user->hasPermission('cuti.pengajuan.approve-langsung') => 'atasan_langsung',
            default => 'petugas_kepegawaian',
        };

        $this->workflowService->rejectByRole(
            $id,
            $user,
            $role,
            $request->validated('alasan')
        );

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Pengajuan telah ditolak.');
    }

    /**
     * Pembatalan pengajuan cuti (draft atau setelah disetujui).
     */
    public function cancel(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        $pengajuan = CutiPengajuan::findOrFail($id);

        if ($pengajuan->state->name() === 'DISETUJUI') {
            $this->workflowService->cancelAfterApproved($id, $user);

            return to_route('cuti.pengajuan.show', $id)
                ->with('success', 'Cuti yang sudah disetujui berhasil dicabut.');
        }

        $this->workflowService->cancelDraft($id, $user);

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Pengajuan berhasil dibatalkan.');
    }

    /**
     * Reassign approver pada pengajuan cuti aktif.
     */
    public function reassign(ReassignApproverRequest $request, string $id): RedirectResponse
    {
        $this->workflowService->reassignApprover(
            $id,
            $request->validated('role'),
            $request->validated('new_nip'),
            $request->user(),
            $request->validated('alasan')
        );

        return to_route('cuti.pengajuan.show', $id)
            ->with('success', 'Approver berhasil di-reassign.');
    }
}
