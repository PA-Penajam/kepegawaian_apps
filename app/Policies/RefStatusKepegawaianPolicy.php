<?php

namespace App\Policies;

use App\Models\User;

class RefStatusKepegawaianPolicy extends RefPolicy
{
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user);
    }

    public function view(User $user, $model): bool
    {
        return parent::view($user, $model);
    }

    public function create(User $user): bool
    {
        return parent::create($user);
    }

    public function update(User $user, $model): bool
    {
        return parent::update($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return parent::delete($user, $model);
    }

    public function restore(User $user, $model): bool
    {
        return parent::restore($user, $model);
    }

    public function forceDelete(User $user, $model): bool
    {
        return parent::forceDelete($user, $model);
    }
}
