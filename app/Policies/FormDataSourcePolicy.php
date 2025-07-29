<?php

namespace App\Policies;

use App\Models\FormMetadata\FormDataSource;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormDataSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormDataSource');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormDataSource $formDataSource): bool
    {
        return $user->can('view FormDataSource');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormDataSource');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormDataSource $formDataSource): bool
    {
        return $user->can('update FormDataSource');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormDataSource $formDataSource): bool
    {
        return $user->can('delete FormDataSource');
    }
}
