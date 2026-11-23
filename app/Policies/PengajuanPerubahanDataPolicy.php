<?php

namespace App\Policies;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;

class PengajuanPerubahanDataPolicy
{
    public function view(Pegawai $user, PengajuanPerubahanData $pengajuan): bool
    {
        return $user->id === $pengajuan->pengaju_id;
    }

    public function viewAny(Pegawai $user): bool
    {
        return $user->hasPermission('pengajuan-perubahan.validate');
    }

    public function approve(Pegawai $user, PengajuanPerubahanData $pengajuan): bool
    {
        return $user->hasPermission('pengajuan-perubahan.validate')
            && $pengajuan->status === StatusPengajuanPerubahanData::Pending
            && $pengajuan->pengaju_id !== $user->id
            && $pengajuan->subject_pegawai_id !== $user->id;
    }

    public function reject(Pegawai $user, PengajuanPerubahanData $pengajuan): bool
    {
        return $user->hasPermission('pengajuan-perubahan.validate')
            && $pengajuan->status === StatusPengajuanPerubahanData::Pending
            && $pengajuan->pengaju_id !== $user->id
            && $pengajuan->subject_pegawai_id !== $user->id;
    }
}
