<?php

namespace App\Policies;

use App\Models\SiebelBusinessService;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelBusinessServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelBusinessService');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelBusinessService $siebelBusinessService): bool
    {
        return $user->can('view SiebelBusinessService');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelBusinessService');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelBusinessService $siebelBusinessService): bool
    {
        return $user->can('update SiebelBusinessService');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelBusinessService $siebelBusinessService): bool
    {
        return $user->can('delete SiebelBusinessService');
    }
}
