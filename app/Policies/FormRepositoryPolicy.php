<?php

namespace App\Policies;

use App\Models\FormRepository;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormRepositoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormRepository');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormRepository $formRepository): bool
    {
        return $user->can('view FormRepository');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormRepository');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormRepository $formRepository): bool
    {
        return $user->can('update FormRepository');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormRepository $formRepository): bool
    {
        return $user->can('delete FormRepository');
    }
}
