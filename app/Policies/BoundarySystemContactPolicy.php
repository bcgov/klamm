<?php

namespace App\Policies;

use App\Models\BoundarySystemContact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemContactPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemContact');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemContact $boundarySystemContact): bool
    {
        return $user->can('view BoundarySystemContact');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemContact');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemContact $boundarySystemContact): bool
    {
        return $user->can('update BoundarySystemContact');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemContact $boundarySystemContact): bool
    {
        return $user->can('delete BoundarySystemContact');
    }
}
