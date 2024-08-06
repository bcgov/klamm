<?php

namespace App\Policies;

use App\Models\BusinessArea;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BusinessAreaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BusinessArea');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BusinessArea $businessArea): bool
    {
        return $user->can('view BusinessArea');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BusinessArea');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BusinessArea $businessArea): bool
    {
        return $user->can('update BusinessArea');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BusinessArea $businessArea): bool
    {
        return $user->can('delete BusinessArea');
    }
}
