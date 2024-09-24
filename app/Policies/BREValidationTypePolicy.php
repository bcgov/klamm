<?php

namespace App\Policies;

use App\Models\BREValidationType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREValidationTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREValidationType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREValidationType $bREValidationType): bool
    {
        return $user->can('view BREValidationType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREValidationType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREValidationType $bREValidationType): bool
    {
        return $user->can('update BREValidationType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREValidationType $bREValidationType): bool
    {
        return $user->can('delete BREValidationType');
    }
}
