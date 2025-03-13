<?php

namespace App\Policies;

use App\Models\BoundarySystemFileFieldMap;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileFieldMapPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileFieldMap');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileFieldMap $boundarySystemFileFieldMap): bool
    {
        return $user->can('view BoundarySystemFileFieldMap');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileFieldMap');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileFieldMap $boundarySystemFileFieldMap): bool
    {
        return $user->can('update BoundarySystemFileFieldMap');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileFieldMap $boundarySystemFileFieldMap): bool
    {
        return $user->can('delete BoundarySystemFileFieldMap');
    }
}
