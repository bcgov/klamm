<?php

namespace App\Policies;

use App\Models\BoundarySystemFileFieldType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemFileFieldTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemFileFieldType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemFileFieldType $boundarySystemFileFieldType): bool
    {
        return $user->can('view BoundarySystemFileFieldType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemFileFieldType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemFileFieldType $boundarySystemFileFieldType): bool
    {
        return $user->can('update BoundarySystemFileFieldType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemFileFieldType $boundarySystemFileFieldType): bool
    {
        return $user->can('delete BoundarySystemFileFieldType');
    }
}
