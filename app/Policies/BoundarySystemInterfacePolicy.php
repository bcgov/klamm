<?php

namespace App\Policies;

use App\Models\BoundarySystemInterface;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemInterfacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemInterface');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemInterface $boundarySystemInterface): bool
    {
        return $user->can('view BoundarySystemInterface');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemInterface');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemInterface $boundarySystemInterface): bool
    {
        return $user->can('update BoundarySystemInterface');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemInterface $boundarySystemInterface): bool
    {
        return $user->can('delete BoundarySystemInterface');
    }
}
