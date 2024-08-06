<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ValueType;
use Illuminate\Auth\Access\Response;

class ValueTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ValueType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ValueType $valueType): bool
    {
        return $user->can('view ValueType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ValueType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ValueType $valueType): bool
    {
        return $user->can('update ValueType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ValueType $valueType): bool
    {
        return $user->can('delete ValueType');
    }
}
