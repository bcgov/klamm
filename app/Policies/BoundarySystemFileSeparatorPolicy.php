<?php

namespace App\Policies;

use App\Models\BoundarySystemFileSeparator;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileSeparatorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileSeparator');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileSeparator $boundarySystemFileSeparator): bool
    {
        return $user->can('view BoundarySystemFileSeparator');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileSeparator');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileSeparator $boundarySystemFileSeparator): bool
    {
        return $user->can('update BoundarySystemFileSeparator');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileSeparator $boundarySystemFileSeparator): bool
    {
        return $user->can('delete BoundarySystemFileSeparator');
    }
}
