<?php

namespace App\Policies;

use App\Models\BoundarySystemFrequency;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFrequencyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFrequency');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFrequency $boundarySystemFrequency): bool
    {
        return $user->can('view BoundarySystemFrequency');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFrequency');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFrequency $boundarySystemFrequency): bool
    {
        return $user->can('update BoundarySystemFrequency');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFrequency $boundarySystemFrequency): bool
    {
        return $user->can('delete BoundarySystemFrequency');
    }
}
