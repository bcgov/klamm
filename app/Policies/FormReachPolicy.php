<?php

namespace App\Policies;

use App\Models\FormReach;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormReachPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormReach');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormReach $formReach): bool
    {
        return $user->can('view FormReach');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormReach');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormReach $formReach): bool
    {
        return $user->can('update FormReach');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormReach $formReach): bool
    {
        return $user->can('delete FormReach');
    }
}
