<?php

namespace App\Policies;

use App\Models\SiebelBusinessComponent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelBusinessComponentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelBusinessComponent');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelBusinessComponent $siebelBusinessComponent): bool
    {
        return $user->can('view SiebelBusinessComponent');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelBusinessComponent');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelBusinessComponent $siebelBusinessComponent): bool
    {
        return $user->can('update SiebelBusinessComponent');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelBusinessComponent $siebelBusinessComponent): bool
    {
        return $user->can('delete SiebelBusinessComponent');
    }
}
