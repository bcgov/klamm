<?php

namespace App\Policies;

use App\Models\BREDataType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREDataTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREDataType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREDataType $bREDataType): bool
    {
        return $user->can('view BREDataType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREDataType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREDataType $bREDataType): bool
    {
        return $user->can('update BREDataType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREDataType $bREDataType): bool
    {
        return $user->can('delete BREDataType');
    }
}
