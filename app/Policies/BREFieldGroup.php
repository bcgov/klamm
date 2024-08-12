<?php

namespace App\Policies;

use App\Models\BREFieldGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREFieldGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREFieldGroup');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREFieldGroup $bREFieldGroup): bool
    {
        return $user->can('view BREFieldGroup');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREFieldGroup');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREFieldGroup $bREFieldGroup): bool
    {
        return $user->can('update BREFieldGroup');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREFieldGroup $bREFieldGroup): bool
    {
        return $user->can('delete BREFieldGroup');
    }
}
