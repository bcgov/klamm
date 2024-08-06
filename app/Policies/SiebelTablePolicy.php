<?php

namespace App\Policies;

use App\Models\SiebelTable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelTablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelTable');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelTable $siebelTable): bool
    {
        return $user->can('view SiebelTable');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelTable');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelTable $siebelTable): bool
    {
        return $user->can('update SiebelTable');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelTable $siebelTable): bool
    {
        return $user->can('delete SiebelTable');
    }
}
