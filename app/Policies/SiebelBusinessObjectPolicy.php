<?php

namespace App\Policies;

use App\Models\SiebelBusinessObject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelBusinessObjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelBusinessObject');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelBusinessObject $siebelBusinessObject): bool
    {
        return $user->can('view SiebelBusinessObject');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelBusinessObject');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelBusinessObject $siebelBusinessObject): bool
    {
        return $user->can('update SiebelBusinessObject');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelBusinessObject $siebelBusinessObject): bool
    {
        return $user->can('delete SiebelBusinessObject');
    }
}
