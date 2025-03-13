<?php

namespace App\Policies;

use App\Models\BoundarySystemFile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFile');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFile $boundarySystemFile): bool
    {
        return $user->can('view BoundarySystemFile');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFile');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFile $boundarySystemFile): bool
    {
        return $user->can('update BoundarySystemFile');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFile $boundarySystemFile): bool
    {
        return $user->can('delete BoundarySystemFile');
    }
}
