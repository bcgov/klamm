<?php

namespace App\Policies;

use App\Models\BoundarySystemProcess;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemProcessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemProcess');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemProcess $boundarySystemProcess): bool
    {
        return $user->can('view BoundarySystemProcess');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemProcess');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemProcess $boundarySystemProcess): bool
    {
        return $user->can('update BoundarySystemProcess');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemProcess $boundarySystemProcess): bool
    {
        return $user->can('delete BoundarySystemProcess');
    }
}
