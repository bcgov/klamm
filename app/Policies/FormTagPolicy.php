<?php

namespace App\Policies;

use App\Models\FormTag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormTagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormTag');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormTag $formTag): bool
    {
        return $user->can('view FormTag');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormTag');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormTag $formTag): bool
    {
        return $user->can('update FormTag');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormTag $formTag): bool
    {
        return $user->can('delete FormTag');
    }
}
