<?php

namespace App\Policies;

use App\Models\BoundarySystemFileField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileFieldPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileField');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileField $boundarySystemFileField): bool
    {
        return $user->can('view BoundarySystemFileField');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileField');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileField $boundarySystemFileField): bool
    {
        return $user->can('update BoundarySystemFileField');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileField $boundarySystemFileField): bool
    {
        return $user->can('delete BoundarySystemFileField');
    }
}
