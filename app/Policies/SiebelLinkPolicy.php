<?php

namespace App\Policies;

use App\Models\SiebelLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelLinkPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelLink');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelLink $siebelLink): bool
    {
        return $user->can('view SiebelLink');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelLink');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelLink $siebelLink): bool
    {
        return $user->can('update SiebelLink');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelLink $siebelLink): bool
    {
        return $user->can('delete SiebelLink');
    }
}
