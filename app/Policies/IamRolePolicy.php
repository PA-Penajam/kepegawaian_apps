<?php

namespace App\Policies;

use App\Models\IamRole;
use App\Models\Pegawai;

class IamRolePolicy
{
    public function viewAny(Pegawai $user): bool
    {
        return $user->hasPermission('iam-manage');
    }

    public function view(Pegawai $user, IamRole $role): bool
    {
        return $role->exists && $user->hasPermission('iam-manage');
    }

    public function create(Pegawai $user): bool
    {
        return $user->hasPermission('iam-manage');
    }

    public function update(Pegawai $user, IamRole $role): bool
    {
        return $role->exists && $user->hasPermission('iam-manage');
    }

    public function delete(Pegawai $user, IamRole $role): bool
    {
        return $role->exists && $user->hasPermission('iam-manage');
    }
}
