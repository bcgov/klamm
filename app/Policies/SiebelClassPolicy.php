<?php

namespace App\Policies;

use App\Models\SiebelClass;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelClassPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelClass');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelClass $siebelClass): bool
    {
        return $user->can('view SiebelClass');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelClass');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelClass $siebelClass): bool
    {
        return $user->can('update SiebelClass');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelClass $siebelClass): bool
    {
        return $user->can('delete SiebelClass');
    }
}
