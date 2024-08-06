<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Auth\Access\Response;

class UserTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any UserType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserType $userType): bool
    {
        return $user->can('view UserType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create UserType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserType $userType): bool
    {
        return $user->can('update UserType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserType $userType): bool
    {
        return $user->can('delete UserType');
    }
}
