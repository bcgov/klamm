<?php

namespace App\Policies;

use App\Models\BoundarySystemFileFieldMapSection;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileFieldMapSectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileFieldMapSection');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileFieldMapSection $boundarySystemFileFieldMapSection): bool
    {
        return $user->can('view BoundarySystemFileFieldMapSection');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileFieldMapSection');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileFieldMapSection $boundarySystemFileFieldMapSection): bool
    {
        return $user->can('update BoundarySystemFileFieldMapSection');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileFieldMapSection $boundarySystemFileFieldMapSection): bool
    {
        return $user->can('delete BoundarySystemFileFieldMapSection');
    }
}
