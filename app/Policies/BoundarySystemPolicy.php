<?php

namespace App\Policies;

use App\Models\BoundarySystem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystem');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystem $boundarySystem): bool
    {
        return $user->can('view BoundarySystem');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystem');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystem $boundarySystem): bool
    {
        return $user->can('update BoundarySystem');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystem $boundarySystem): bool
    {
        return $user->can('delete BoundarySystem');
    }
}
