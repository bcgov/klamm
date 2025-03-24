<?php

namespace App\Policies;

use App\Models\PopularPageSystemMessage;
use App\Models\User;

class PopularPageSystemMessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any PopularPageSystemMessage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PopularPageSystemMessage $popularPageSystemMessage): bool
    {
        return $user->can('view PopularPageSystemMessage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create PopularPageSystemMessage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PopularPageSystemMessage $popularPageSystemMessage): bool
    {
        return $user->can('update PopularPageSystemMessage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PopularPageSystemMessage $popularPageSystemMessage): bool
    {
        return $user->can('delete PopularPageSystemMessage');
    }
}
