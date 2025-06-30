<?php

namespace App\Policies;

use App\Models\FormMetadata\FormLocation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormLocationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormLocation');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormLocation $formLocation): bool
    {
        return $user->can('view FormLocation');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormLocation');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormLocation $formLocation): bool
    {
        return $user->can('update FormLocation');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormLocation $formLocation): bool
    {
        return $user->can('delete FormLocation');
    }
}
