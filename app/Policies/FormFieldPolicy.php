<?php

namespace App\Policies;

use App\Models\FormField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormFieldPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormField');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormField $formField): bool
    {
        return $user->can('view FormField');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormField');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormField $formField): bool
    {
        return $user->can('update FormField');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormField $formField): bool
    {
        return $user->can('delete FormField');
    }
}
