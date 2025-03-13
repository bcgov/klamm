<?php

namespace App\Policies;

use App\Models\BoundarySystemModeOfTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoundarySystemModeOfTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BoundarySystemModeOfTransfer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BoundarySystemModeOfTransfer $boundarySystemModeOfTransfer): bool
    {
        return $user->can('view BoundarySystemModeOfTransfer');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BoundarySystemModeOfTransfer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BoundarySystemModeOfTransfer $boundarySystemModeOfTransfer): bool
    {
        return $user->can('update BoundarySystemModeOfTransfer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BoundarySystemModeOfTransfer $boundarySystemModeOfTransfer): bool
    {
        return $user->can('delete BoundarySystemModeOfTransfer');
    }
}
