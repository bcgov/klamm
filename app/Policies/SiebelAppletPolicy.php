<?php

namespace App\Policies;

use App\Models\SiebelApplet;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelAppletPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelApplet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelApplet $siebelApplet): bool
    {
        return $user->can('view SiebelApplet');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelApplet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelApplet $siebelApplet): bool
    {
        return $user->can('update SiebelApplet');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelApplet $siebelApplet): bool
    {
        return $user->can('delete SiebelApplet');
    }
}
