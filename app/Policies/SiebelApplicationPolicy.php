<?php

namespace App\Policies;

use App\Models\SiebelApplication;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelApplicationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelApplication');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelApplication $siebelApplication): bool
    {
        return $user->can('view SiebelApplication');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelApplication');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelApplication $siebelApplication): bool
    {
        return $user->can('update SiebelApplication');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelApplication $siebelApplication): bool
    {
        return $user->can('delete SiebelApplication');
    }
}
