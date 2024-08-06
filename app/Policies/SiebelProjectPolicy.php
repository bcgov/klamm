<?php

namespace App\Policies;

use App\Models\SiebelProject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelProject');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelProject $siebelProject): bool
    {
        return $user->can('view SiebelProject');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelProject');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelProject $siebelProject): bool
    {
        return $user->can('update SiebelProject');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelProject $siebelProject): bool
    {
        return $user->can('delete SiebelProject');
    }
}
