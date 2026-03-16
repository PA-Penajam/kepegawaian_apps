<?php

namespace App\Policies;

use App\Models\Pegawai;

class PegawaiPolicy
{
    public function viewAny(Pegawai $user): bool
    {
        return $user->hasPermission('pegawai.view');
    }

    public function view(Pegawai $user, Pegawai $pegawai): bool
    {
        return $pegawai->exists && $user->hasPermission('pegawai.view');
    }

    public function create(Pegawai $user): bool
    {
        return $user->hasPermission('pegawai.create');
    }

    public function update(Pegawai $user, Pegawai $pegawai): bool
    {
        return $pegawai->exists && $user->hasPermission('pegawai.update');
    }

    public function delete(Pegawai $user, Pegawai $pegawai): bool
    {
        return $pegawai->exists && $user->hasPermission('pegawai.delete');
    }
}
