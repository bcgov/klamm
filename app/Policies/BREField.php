<?php

namespace App\Policies;

use App\Models\BREField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREFieldPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREField');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BREField $bREField): bool
    {
        return $user->can('view BREField');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREField');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BREField $bREField): bool
    {
        return $user->can('update BREField');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BREField $bREField): bool
    {
        return $user->can('delete BREField');
    }
}
