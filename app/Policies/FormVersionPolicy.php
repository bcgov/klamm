<?php

namespace App\Policies;

use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FormVersionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FormVersion');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FormVersion $formVersion): bool
    {
        return $user->can('view FormVersion');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FormVersion');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FormVersion $formVersion): bool
    {
        if (in_array($formVersion->status, ['published', 'archived'])) {
            return false;
        }

        return $user->can('update FormVersion');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FormVersion $formVersion): bool
    {
        return $user->can('delete FormVersion');
    }
}
