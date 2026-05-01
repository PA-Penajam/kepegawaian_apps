<?php

namespace App\Services\Cuti;

use App\Exceptions\Cuti\CancelTidakDiizinkanException;
use App\Exceptions\Cuti\TransitionTidakValidException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanApprovalStep;
use App\Models\Cuti\CutiPengajuanApproverHistory;
use App\Models\Cuti\CutiPengajuanStateHistory;
use App\Models\Pegawai;
use App\Notifications\Cuti\PengajuanDisetujui;
use App\Notifications\Cuti\PengajuanDitolak;
use App\Notifications\Cuti\PengajuanMenungguApproval;
use App\States\Cuti\DibatalkanState;
use App\States\Cuti\DicabutSetelahDisetujuiState;
use App\States\Cuti\DisetujuiAtasanState;
use App\States\Cuti\DisetujuiState;
use App\States\Cuti\DitolakAtasanState;
use App\States\Cuti\DitolakKepegawaianState;
use App\States\Cuti\DitolakPejabatState;
use App\States\Cuti\DiverifikasiState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(
        private SaldoLedgerService $saldoService,
        private EventDispatcherService $eventDispatcher,
    ) {}

    /**
     * Verifikasi pengajuan oleh petugas kepegawaian.
     * Transisi: DIAJUKAN → DIVERIFIKASI
     */
    public function verify(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DiverifikasiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DIVERIFIKASI tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.verify');

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DiverifikasiState::class);

            $this->logApprovalStep($pengajuan, 'petugas_kepegawaian', 'verify', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DIVERIFIKASI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.diverifikasi', $pengajuan);

            // Kirim notifikasi ke atasan langsung
            $atasan = $pengajuan->atasanLangsungCurrent;
            $atasan?->notify(new PengajuanMenungguApproval($pengajuan, 'atasan_langsung'));
        });
    }

    /**
     * Persetujuan oleh atasan langsung.
     * Transisi: DIVERIFIKASI → DISETUJUI_ATASAN
     */
    public function approveAtasan(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DisetujuiAtasanState::class)) {
                throw new TransitionTidakValidException('Transisi ke DISETUJUI_ATASAN tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.approve-langsung');

            // Validasi aktor adalah atasan langsung yang ditunjuk
            if ($pengajuan->atasan_langsung_current_nip !== $aktor->nip) {
                throw new AuthorizationException('Anda bukan atasan langsung yang ditunjuk untuk pengajuan ini.');
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DisetujuiAtasanState::class);

            $this->logApprovalStep($pengajuan, 'atasan_langsung', 'approve', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DISETUJUI_ATASAN', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.disetujui_atasan', $pengajuan);

            // Kirim notifikasi ke pejabat berwenang
            $pejabat = $pengajuan->pejabatBerwenangCurrent;
            $pejabat?->notify(new PengajuanMenungguApproval($pengajuan, 'pejabat_berwenang'));
        });
    }

    /**
     * Persetujuan oleh pejabat berwenang (final approval).
     * Transisi: DISETUJUI_ATASAN → DISETUJUI
     * Untuk CT: commit saldo ledger dari pending ke confirmed.
     */
    public function approvePejabat(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DisetujuiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DISETUJUI tidak valid dari state saat ini.');
            }

            $this->guardAuthorization($aktor, 'cuti.pengajuan.approve-pejabat');

            // Lock alokasi untuk CT dan commit saldo
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
                    ->where('jenis_cuti_kode', 'CT')
                    ->where('tahun_hak', $pengajuan->tahunHak())
                    ->lockForUpdate()
                    ->first();

                $this->saldoService->commitConfirmed($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DisetujuiState::class);
            $pengajuan->approved_at = now();
            $pengajuan->save();

            $this->logApprovalStep($pengajuan, 'pejabat_berwenang', 'approve', $aktor, $catatan);
            $this->logStateHistory($pengajuan, $fromState, 'DISETUJUI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.disetujui', $pengajuan);

            // Kirim notifikasi ke pegawai pemohon
            $pengajuan->pegawai?->notify(new PengajuanDisetujui($pengajuan));
        });
    }

    /**
     * Penolakan generik untuk 3 role: kepegawaian, atasan, pejabat.
     * Untuk CT: void pending ledger entries.
     */
    public function rejectByRole(string $id, Pegawai $aktor, string $role, string $alasan): void
    {
        DB::transaction(function () use ($id, $aktor, $role, $alasan): void {
            $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();

            $targetState = match ($role) {
                'petugas_kepegawaian' => DitolakKepegawaianState::class,
                'atasan_langsung' => DitolakAtasanState::class,
                'pejabat_berwenang' => DitolakPejabatState::class,
                default => throw new \InvalidArgumentException("Role tidak dikenali: {$role}"),
            };

            if (! $pengajuan->state->canTransitionTo($targetState)) {
                throw new TransitionTidakValidException('Transisi penolakan tidak valid dari state saat ini.');
            }

            $permMap = [
                'petugas_kepegawaian' => 'cuti.pengajuan.verify',
                'atasan_langsung' => 'cuti.pengajuan.approve-langsung',
                'pejabat_berwenang' => 'cuti.pengajuan.approve-pejabat',
            ];
            $this->guardAuthorization($aktor, $permMap[$role]);

            // Void pending ledger untuk CT
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                $this->saldoService->voidPending($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo($targetState);
            $pengajuan->rejected_at = now();
            $pengajuan->rejection_reason = $alasan;
            $pengajuan->save();

            $this->logApprovalStep($pengajuan, $role, 'reject', $aktor, $alasan);
            $this->logStateHistory($pengajuan, $fromState, $pengajuan->state->name(), $aktor, $alasan);
            $this->eventDispatcher->dispatch('cuti.ditolak', $pengajuan);

            // Kirim notifikasi ke pegawai pemohon
            $pengajuan->pegawai?->notify(new PengajuanDitolak($pengajuan));
        });
    }

    /**
     * Pembatalan pengajuan dalam state DRAFT.
     * Tidak ada interaksi ledger karena belum ada debit.
     */
    public function cancelDraft(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DibatalkanState::class)) {
                throw new TransitionTidakValidException('Transisi ke DIBATALKAN tidak valid dari state saat ini.');
            }

            // Harus pemilik pengajuan + punya permission cancel-own, atau admin cancel-any
            $isOwner = $aktor->nip === $pengajuan->pegawai_nip;
            if ($isOwner && $aktor->hasPermission('cuti.pengajuan.cancel-own')) {
                // ok — pemilik membatalkan miliknya
            } elseif ($aktor->hasPermission('cuti.pengajuan.cancel-any')) {
                // ok — admin membatalkan
            } else {
                throw new AuthorizationException('Tidak berhak membatalkan pengajuan ini.');
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DibatalkanState::class);
            $pengajuan->cancelled_at = now();
            $pengajuan->save();

            $this->logStateHistory($pengajuan, $fromState, 'DIBATALKAN', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.dibatalkan', $pengajuan);
        });
    }

    /**
     * Pencabutan cuti yang sudah disetujui.
     * Guard: cek boleh_dicabut_setelah_disetujui pada jenis cuti.
     * Untuk CT: proses refund saldo.
     */
    public function cancelAfterApproved(string $pengajuanId, Pegawai $aktor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($pengajuanId, $aktor, $catatan): void {
            $pengajuan = CutiPengajuan::where('id', $pengajuanId)->lockForUpdate()->firstOrFail();

            if (! $pengajuan->state->canTransitionTo(DicabutSetelahDisetujuiState::class)) {
                throw new TransitionTidakValidException('Transisi ke DICABUT_SETELAH_DISETUJUI tidak valid dari state saat ini.');
            }

            // Pemilik (cancel-own) atau admin (cancel-any)
            $isOwner = $aktor->nip === $pengajuan->pegawai_nip;
            if ($isOwner && $aktor->hasPermission('cuti.pengajuan.cancel-own')) {
                // ok
            } elseif ($aktor->hasPermission('cuti.pengajuan.cancel-any')) {
                // ok
            } else {
                throw new AuthorizationException('Tidak berhak membatalkan cuti yang sudah disetujui.');
            }

            // Cek apakah jenis cuti boleh dicabut setelah disetujui
            $jenisCuti = $pengajuan->jenisCuti;
            if (! $jenisCuti->boleh_dicabut_setelah_disetujui) {
                throw new CancelTidakDiizinkanException(
                    "Jenis cuti {$jenisCuti->nama} tidak boleh dicabut setelah disetujui."
                );
            }

            // Refund saldo untuk CT
            if ($pengajuan->jenis_cuti_kode === 'CT') {
                $this->saldoService->processRefund($pengajuan);
            }

            $fromState = $pengajuan->state->name();
            $pengajuan->state->transitionTo(DicabutSetelahDisetujuiState::class);
            $pengajuan->cancelled_at = now();
            $pengajuan->save();

            $this->logStateHistory($pengajuan, $fromState, 'DICABUT_SETELAH_DISETUJUI', $aktor, $catatan);
            $this->eventDispatcher->dispatch('cuti.dicabut', $pengajuan);
        });
    }

    /**
     * Reassign approver pada pengajuan aktif.
     * Hanya admin yang boleh melakukan reassignment.
     */
    public function reassignApprover(string $id, string $role, string $newNip, Pegawai $aktor, string $alasan): void
    {
        DB::transaction(function () use ($id, $role, $newNip, $aktor, $alasan): void {
            $pengajuan = CutiPengajuan::where('id', $id)->lockForUpdate()->firstOrFail();
            $this->guardAuthorization($aktor, 'cuti.pengajuan.reassign');

            $col = "{$role}_current_nip";
            $oldNip = $pengajuan->$col;
            $pengajuan->$col = $newNip;
            $pengajuan->save();

            CutiPengajuanApproverHistory::create([
                'pengajuan_id' => $id,
                'role' => $role,
                'from_pegawai_nip' => $oldNip,
                'to_pegawai_nip' => $newNip,
                'alasan' => $alasan,
                'aktor_pegawai_nip' => $aktor->nip,
            ]);
        });
    }

    /**
     * Validasi authorization berdasarkan permission IAM.
     *
     * @throws AuthorizationException
     */
    private function guardAuthorization(Pegawai $aktor, string $permission): void
    {
        if (! $aktor->hasPermission($permission)) {
            throw new AuthorizationException("Tidak memiliki permission: {$permission}");
        }
    }

    /**
     * Catat langkah approval ke tabel audit.
     */
    private function logApprovalStep(
        CutiPengajuan $p,
        string $role,
        string $action,
        Pegawai $aktor,
        ?string $catatan = null,
    ): void {
        CutiPengajuanApprovalStep::create([
            'pengajuan_id' => $p->id,
            'role' => $role,
            'action' => $action,
            'aktor_pegawai_nip' => $aktor->nip,
            'catatan' => $catatan,
            'acted_at' => now(),
        ]);
    }

    /**
     * Catat perubahan state ke tabel history.
     */
    private function logStateHistory(
        CutiPengajuan $p,
        string $from,
        string $to,
        Pegawai $aktor,
        ?string $catatan = null,
    ): void {
        CutiPengajuanStateHistory::create([
            'pengajuan_id' => $p->id,
            'state_from' => $from,
            'state_to' => $to,
            'aktor_pegawai_nip' => $aktor->nip,
            'catatan' => $catatan,
        ]);
    }
}
