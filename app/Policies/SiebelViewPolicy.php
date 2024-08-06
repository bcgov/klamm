<?php

namespace App\Policies;

use App\Models\SiebelView;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelViewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelView');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelView $siebelView): bool
    {
        return $user->can('view SiebelView');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelView');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelView $siebelView): bool
    {
        return $user->can('update SiebelView');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelView $siebelView): bool
    {
        return $user->can('delete SiebelView');
    }
}
