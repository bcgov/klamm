<?php

namespace App\Policies;

use App\Models\SiebelScreen;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelScreenPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelScreen');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelScreen $siebelScreen): bool
    {
        return $user->can('view SiebelScreen');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelScreen');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelScreen $siebelScreen): bool
    {
        return $user->can('update SiebelScreen');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelScreen $siebelScreen): bool
    {
        return $user->can('delete SiebelScreen');
    }
}
