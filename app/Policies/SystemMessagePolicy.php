<?php

namespace App\Policies;

use App\Models\SystemMessage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SystemMessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SystemMessage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SystemMessage $systemMessage): bool
    {
        return $user->can('view SystemMessage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SystemMessage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SystemMessage $systemMessage): bool
    {
        return $user->can('update SystemMessage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SystemMessage $systemMessage): bool
    {
        return $user->can('delete SystemMessage');
    }
}
