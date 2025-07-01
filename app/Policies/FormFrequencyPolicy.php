<?php

namespace App\Policies;

use App\Models\FormMetadata\FormFrequency;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormFrequencyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormFrequency');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormFrequency $formFrequency): bool
    {
        return $user->can('view FormFrequency');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormFrequency');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormFrequency $formFrequency): bool
    {
        return $user->can('update FormFrequency');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormFrequency $formFrequency): bool
    {
        return $user->can('delete FormFrequency');
    }
}
