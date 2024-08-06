<?php

namespace App\Policies;

use App\Models\SiebelValue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelValuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelValue');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelValue $siebelValue): bool
    {
        return $user->can('view SiebelValue');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelValue');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelValue $siebelValue): bool
    {
        return $user->can('update SiebelValue');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelValue $siebelValue): bool
    {
        return $user->can('delete SiebelValue');
    }
}
