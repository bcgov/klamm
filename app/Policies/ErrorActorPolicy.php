<?php

namespace App\Policies;

use App\Models\ErrorActor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ErrorActorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ErrorActor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ErrorActor $errorActor): bool
    {
        return $user->can('view ErrorActor');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ErrorActor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ErrorActor $errorActor): bool
    {
        return $user->can('update ErrorActor');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ErrorActor $errorActor): bool
    {
        return $user->can('delete ErrorActor');
    }
}
