<?php

namespace App\Policies;

use App\Models\IamRole as RefRole;
use App\Models\Pegawai;

class RefRolePolicy extends RefPolicy
{
    public function viewAny(Pegawai $user): bool
    {
        return parent::viewAny($user);
    }

    public function view(Pegawai $user, $model): bool
    {
        return parent::view($user, $model);
    }

    public function create(Pegawai $user): bool
    {
        return parent::create($user);
    }

    public function update(Pegawai $user, $model): bool
    {
        return parent::update($user, $model);
    }

    public function delete(Pegawai $user, $model): bool
    {
        if ($model instanceof RefRole && $model->is_system) {
            return false;
        }

        return parent::delete($user, $model);
    }

    public function restore(Pegawai $user, $model): bool
    {
        return parent::restore($user, $model);
    }

    public function forceDelete(Pegawai $user, $model): bool
    {
        return parent::forceDelete($user, $model);
    }
}
