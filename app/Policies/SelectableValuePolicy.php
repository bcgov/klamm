<?php

namespace App\Policies;

use App\Models\SelectableValue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SelectableValuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // return $user->can('view-any SelectableValue');
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SelectableValue $selectableValue): bool
    {
        return $user->can('view SelectableValue');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SelectableValue');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SelectableValue $selectableValue): bool
    {
        return $user->can('update SelectableValue');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SelectableValue $selectableValue): bool
    {
        return $user->can('delete SelectableValue');
    }
}
