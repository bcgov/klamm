<?php

namespace App\Policies;

use App\Models\BoundarySystemSystem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemSystemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemSystem');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemSystem $boundarySystemSystem): bool
    {
        return $user->can('view BoundarySystemSystem');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemSystem');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemSystem $boundarySystemSystem): bool
    {
        return $user->can('update BoundarySystemSystem');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemSystem $boundarySystemSystem): bool
    {
        return $user->can('delete BoundarySystemSystem');
    }
}
