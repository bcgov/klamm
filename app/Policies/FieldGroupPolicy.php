<?php

namespace App\Policies;

use App\Models\FieldGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FieldGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FieldGroup');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FieldGroup $fieldGroup): bool
    {
        return $user->can('view FieldGroup');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FieldGroup');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FieldGroup $fieldGroup): bool
    {
        return $user->can('update FieldGroup');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FieldGroup $fieldGroup): bool
    {
        return $user->can('delete FieldGroup');
    }
}
