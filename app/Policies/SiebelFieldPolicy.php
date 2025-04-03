<?php

namespace App\Policies;

use App\Models\SiebelField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelFieldPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelField');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelField $siebelField): bool
    {
        return $user->can('view SiebelField');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelField');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelField $siebelField): bool
    {
        return $user->can('update SiebelField');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelField $siebelField): bool
    {
        return $user->can('delete SiebelField');
    }
}
