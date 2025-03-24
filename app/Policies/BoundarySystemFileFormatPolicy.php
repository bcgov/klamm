<?php

namespace App\Policies;

use App\Models\BoundarySystemFileFormat;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileFormatPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileFormat');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileFormat $boundarySystemFileFormat): bool
    {
        return $user->can('view BoundarySystemFileFormat');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileFormat');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileFormat $boundarySystemFileFormat): bool
    {
        return $user->can('update BoundarySystemFileFormat');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileFormat $boundarySystemFileFormat): bool
    {
        return $user->can('delete BoundarySystemFileFormat');
    }
}
