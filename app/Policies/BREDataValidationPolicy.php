<?php

namespace App\Policies;

use App\Models\BREDataValidation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREDataValidationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREDataValidation');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREDataValidation $bREDataValidation): bool
    {
        return $user->can('view BREDataValidation');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREDataValidation');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREDataValidation $bREDataValidation): bool
    {
        return $user->can('update BREDataValidation');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREDataValidation $bREDataValidation): bool
    {
        return $user->can('delete BREDataValidation');
    }
}
