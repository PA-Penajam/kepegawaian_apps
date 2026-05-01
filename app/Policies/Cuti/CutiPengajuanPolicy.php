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
     * Verifikasi pengajuan cuti (petugas kepegawaian).
     */
    public function verify(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.verify');
    }

    /**
     * Approve oleh atasan langsung (guard: NIP harus cocok).
     */
    public function approveLangsung(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.approve-langsung')
            && $user->nip === $pengajuan->atasan_langsung_current_nip;
    }

    /**
     * Approve oleh pejabat berwenang.
     */
    public function approvePejabat(Pegawai $user, CutiPengajuan $pengajuan): bool
    {
        return $user->hasPermission('cuti.pengajuan.approve-pejabat');
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
