<?php

namespace App\Policies;

use App\Models\ErrorSource;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ErrorSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ErrorSource');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ErrorSource $errorSource): bool
    {
        return $user->can('view ErrorSource');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ErrorSource');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ErrorSource $errorSource): bool
    {
        return $user->can('update ErrorSource');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ErrorSource $errorSource): bool
    {
        return $user->can('delete ErrorSource');
    }
}
