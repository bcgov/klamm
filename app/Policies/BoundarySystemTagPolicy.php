<?php

namespace App\Policies;

use App\Models\BoundarySystemTag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemTagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemTag');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemTag $boundarySystemTag): bool
    {
        return $user->can('view BoundarySystemTag');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemTag');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemTag $boundarySystemTag): bool
    {
        return $user->can('update BoundarySystemTag');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemTag $boundarySystemTag): bool
    {
        return $user->can('delete BoundarySystemTag');
    }
}
