<?php

namespace App\Policies;

use App\Models\Pegawai;

abstract class RefPolicy
{
    public function viewAny(Pegawai $user): bool
    {
        return $user->hasAnyPermission('referensi.view', 'rbac.manage');
    }

    public function view(Pegawai $user, $model): bool
    {
        return $model->exists && $user->hasAnyPermission('referensi.view', 'rbac.manage');
    }

    public function create(Pegawai $user): bool
    {
        return $user->hasAnyPermission('referensi.create', 'rbac.manage');
    }

    public function update(Pegawai $user, $model): bool
    {
        return $model->exists && $user->hasAnyPermission('referensi.update', 'rbac.manage');
    }

    public function delete(Pegawai $user, $model): bool
    {
        return $model->exists && $user->hasAnyPermission('referensi.delete', 'rbac.manage');
    }

    public function restore(Pegawai $user, $model): bool
    {
        return $model->exists && $user->hasPermission('rbac.manage');
    }

    public function forceDelete(Pegawai $user, $model): bool
    {
        return $model->exists && $user->hasPermission('rbac.manage');
    }
}
