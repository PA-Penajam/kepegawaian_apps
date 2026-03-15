<?php

namespace App\Policies;

use App\Models\RefJenisDokumen;
use App\Models\User;

class RefJenisDokumenPolicy extends RefPolicy
{
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user);
    }

    public function view(User $user, RefJenisDokumen $refJenisDokumen): bool
    {
        return parent::view($user, $refJenisDokumen);
    }

    public function create(User $user): bool
    {
        return parent::create($user);
    }

    public function update(User $user, RefJenisDokumen $refJenisDokumen): bool
    {
        return parent::update($user, $refJenisDokumen);
    }

    public function delete(User $user, RefJenisDokumen $refJenisDokumen): bool
    {
        return parent::delete($user, $refJenisDokumen);
    }

    public function restore(User $user, RefJenisDokumen $refJenisDokumen): bool
    {
        return parent::restore($user, $refJenisDokumen);
    }

    public function forceDelete(User $user, RefJenisDokumen $refJenisDokumen): bool
    {
        return parent::forceDelete($user, $refJenisDokumen);
    }
}
