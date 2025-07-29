<?php

namespace App\Policies;

use App\Models\FormMetadata\FormInterface;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormInterfacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormInterface');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormInterface $formInterface): bool
    {
        return $user->can('view FormInterface');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormInterface');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormInterface $formInterface): bool
    {
        return $user->can('update FormInterface');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormInterface $formInterface): bool
    {
        return $user->can('delete FormInterface');
    }
}
