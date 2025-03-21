<?php

namespace App\Policies;

use App\Models\ErrorDataGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ErrorDataGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ErrorDataGroup');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ErrorDataGroup $errorDataGroup): bool
    {
        return $user->can('view ErrorDataGroup');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ErrorDataGroup');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ErrorDataGroup $errorDataGroup): bool
    {
        return $user->can('update ErrorDataGroup');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ErrorDataGroup $errorDataGroup): bool
    {
        return $user->can('delete ErrorDataGroup');
    }
}
