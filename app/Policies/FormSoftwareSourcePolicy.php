<?php

namespace App\Policies;

use App\Models\FormMetadata\FormSoftwareSource;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormSoftwareSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormSoftwareSource');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormSoftwareSource $formSoftwareSource): bool
    {
        return $user->can('view FormSoftwareSource');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormSoftwareSource');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormSoftwareSource $formSoftwareSource): bool
    {
        return $user->can('update FormSoftwareSource');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormSoftwareSource $formSoftwareSource): bool
    {
        return $user->can('delete FormSoftwareSource');
    }
}
