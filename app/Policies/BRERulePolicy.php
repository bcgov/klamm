<?php

namespace App\Policies;

use App\Models\BRERule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BRERulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BRERule');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BRERule $bRERule): bool
    {
        return $user->can('view BRERule');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BRERule');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BRERule $bRERule): bool
    {
        return $user->can('update BRERule');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BRERule $bRERule): bool
    {
        return $user->can('delete BRERule');
    }
}
