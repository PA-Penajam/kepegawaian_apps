<?php

namespace App\Policies;

use App\Models\User;

abstract class RefPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, $model): bool
    {
        return $model->exists && $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, $model): bool
    {
        return $model->exists && $this->canManage($user);
    }

    public function delete(User $user, $model): bool
    {
        return $model->exists && $this->canManage($user);
    }

    public function restore(User $user, $model): bool
    {
        return $model->exists && $this->canManage($user);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $model->exists && $user->isAdmin();
    }

    protected function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }
}
