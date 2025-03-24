<?php

namespace App\Policies;

use App\Models\ErrorEntity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ErrorEntityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ErrorEntity');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ErrorEntity $errorEntity): bool
    {
        return $user->can('view ErrorEntity');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ErrorEntity');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ErrorEntity $errorEntity): bool
    {
        return $user->can('update ErrorEntity');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ErrorEntity $errorEntity): bool
    {
        return $user->can('delete ErrorEntity');
    }
}
