<?php

namespace App\Policies;

use App\Models\IamPermission;
use App\Models\Pegawai;

class IamPermissionPolicy
{
    public function viewAny(Pegawai $user): bool
    {
        return $user->hasPermission('iam.manage');
    }

    public function view(Pegawai $user, IamPermission $permission): bool
    {
        return $permission->exists && $user->hasPermission('iam.manage');
    }

    public function create(Pegawai $user): bool
    {
        return $user->hasPermission('iam.manage');
    }

    public function update(Pegawai $user, IamPermission $permission): bool
    {
        return $permission->exists && $user->hasPermission('iam.manage');
    }

    public function delete(Pegawai $user, IamPermission $permission): bool
    {
        return $permission->exists && $user->hasPermission('iam.manage');
    }
}
