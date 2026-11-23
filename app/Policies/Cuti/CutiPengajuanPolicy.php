<?php

namespace App\Policies\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;

class CutiPengajuanPolicy
{
    /**
     * Lihat pengajuan cuti milik sendiri.
     */
    public function viewOwn(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->nip === $pengajuan->pegawai_nip
            && $user->hasPermission('cuti.pengajuan.view-own');
    }

    /**
     * Lihat pengajuan cuti tim (atasan langsung/pejabat berwenang).
     */
    public function viewTeam(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.view-team')
            && ($user->nip === $pengajuan->atasan_langsung_current_nip
                || $user->nip === $pengajuan->pejabat_berwenang_current_nip);
    }

    /**
     * Lihat semua pengajuan cuti (admin/kepegawaian).
     */
    public function viewAll(Pegawai $user): bool
    {
        return $user->hasPermission('cuti.pengajuan.view-all');
    }

    /**
     * Verifikasi pengajuan cuti (petugas kepegawaian yang ditunjuk, bukan diri sendiri).
     */
    public function verify(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.verify')
            && $user->nip === $pengajuan->petugas_kepegawaian_current_nip
            && $user->nip !== $pengajuan->pegawai_nip;
    }

    /**
     * Approve oleh atasan langsung (guard: NIP harus cocok dan bukan diri sendiri).
     */
    public function approveLangsung(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.approve-langsung')
            && $user->nip === $pengajuan->atasan_langsung_current_nip
            && $user->nip !== $pengajuan->pegawai_nip;
    }

    /**
     * Approve oleh pejabat berwenang (guard: NIP harus cocok dan bukan diri sendiri).
     */
    public function approvePejabat(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.approve-pejabat')
            && $user->nip === $pengajuan->pejabat_berwenang_current_nip
            && $user->nip !== $pengajuan->pegawai_nip;
    }

    /**
     * Tolak pengajuan cuti berdasarkan state aktif saat ini.
     */
    public function reject(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return match ($pengajuan->state?->name()) {
            'DIAJUKAN' => $this->verify($user, $pengajuan),
            'DIVERIFIKASI' => $this->approveLangsung($user, $pengajuan),
            'DISETUJUI_ATASAN' => $this->approvePejabat($user, $pengajuan),
            default => $user->nip !== $pengajuan->pegawai_nip
                && $user->hasAnyPermission(
                    'cuti.pengajuan.verify',
                    'cuti.pengajuan.approve-langsung',
                    'cuti.pengajuan.approve-pejabat',
                ),
        };
    }

    /**
     * Batalkan pengajuan cuti milik sendiri.
     */
    public function cancelOwn(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->nip === $pengajuan->pegawai_nip
            && $user->hasPermission('cuti.pengajuan.cancel-own');
    }

    /**
     * Batalkan pengajuan cuti apapun (admin).
     */
    public function cancelAny(Pegawai $user): bool
    {
        return $user->hasPermission('cuti.pengajuan.cancel-any');
    }

    /**
     * Reassign approver pada pengajuan cuti (admin).
     */
    public function reassign(Pegawai $user): bool
    {
        return $user->hasPermission('cuti.pengajuan.reassign');
    }
}
