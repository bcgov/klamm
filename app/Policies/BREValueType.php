<?php

namespace App\Policies;

use App\Models\BREValueType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREValueTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREValueType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREValueType $bREValueType): bool
    {
        return $user->can('view BREValueType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREValueType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREValueType $bREValueType): bool
    {
        return $user->can('update BREValueType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREValueType $bREValueType): bool
    {
        return $user->can('delete BREValueType');
    }
}
