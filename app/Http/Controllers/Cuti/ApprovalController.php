<?php

namespace App\Http\Controllers\Cuti;

use App\Exceptions\Cuti\CancelTidakDiizinkanException;
use App\Exceptions\Cuti\TransitionTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\ApproveRequest;
use App\Http\Requests\Cuti\ReassignApproverRequest;
use App\Http\Requests\Cuti\RejectRequest;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
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
        try {
            $this->workflowService->verify(
                $id,
                $request->user(),
                $request->validated('catatan')
            );

            return to_route('cuti.pengajuan.show', $id)
                ->with('success', 'Pengajuan berhasil diverifikasi.');
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk memverifikasi pengajuan ini.');
        } catch (TransitionTidakValidException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memverifikasi pengajuan. Silakan coba lagi.');
        }
    }

    /**
     * Persetujuan oleh atasan langsung.
     */
    public function approveAtasan(ApproveRequest $request, string $id): RedirectResponse
    {
        try {
            $this->workflowService->approveAtasan(
                $id,
                $request->user(),
                $request->validated('catatan')
            );

            return to_route('cuti.pengajuan.show', $id)
                ->with('success', 'Pengajuan berhasil disetujui oleh atasan langsung.');
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk menyetujui pengajuan ini.');
        } catch (TransitionTidakValidException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menyetujui pengajuan. Silakan coba lagi.');
        }
    }

    /**
     * Persetujuan oleh pejabat berwenang (final).
     */
    public function approvePejabat(ApproveRequest $request, string $id): RedirectResponse
    {
        try {
            $this->workflowService->approvePejabat(
                $id,
                $request->user(),
                $request->validated('catatan')
            );

            return to_route('cuti.pengajuan.show', $id)
                ->with('success', 'Pengajuan berhasil disetujui oleh pejabat berwenang.');
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk menyetujui pengajuan ini.');
        } catch (TransitionTidakValidException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menyetujui pengajuan. Silakan coba lagi.');
        }
    }

    /**
     * Penolakan pengajuan cuti berdasarkan role aktor.
     */
    public function reject(RejectRequest $request, string $id): RedirectResponse
    {
        try {
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
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk menolak pengajuan ini.');
        } catch (TransitionTidakValidException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menolak pengajuan. Silakan coba lagi.');
        }
    }

    /**
     * Pembatalan pengajuan cuti (draft atau setelah disetujui).
     */
    public function cancel(Request $request, string $id): RedirectResponse
    {
        try {
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
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk membatalkan pengajuan ini.');
        } catch (TransitionTidakValidException $e) {
            return back()->with('error', $e->getMessage());
        } catch (CancelTidakDiizinkanException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat membatalkan pengajuan. Silakan coba lagi.');
        }
    }

    /**
     * Reassign approver pada pengajuan cuti aktif.
     */
    public function reassign(ReassignApproverRequest $request, string $id): RedirectResponse
    {
        try {
            $this->workflowService->reassignApprover(
                $id,
                $request->validated('role'),
                $request->validated('new_nip'),
                $request->user(),
                $request->validated('alasan')
            );

            return to_route('cuti.pengajuan.show', $id)
                ->with('success', 'Approver berhasil di-reassign.');
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Anda tidak memiliki izin untuk melakukan reassign approver.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat melakukan reassign approver. Silakan coba lagi.');
        }
    }
}
