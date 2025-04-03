<?php

namespace App\Policies;

use App\Models\ErrorIntegrationState;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ErrorIntegrationStatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ErrorIntegrationState');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ErrorIntegrationState $errorIntegrationState): bool
    {
        return $user->can('view ErrorIntegrationState');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ErrorIntegrationState');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ErrorIntegrationState $errorIntegrationState): bool
    {
        return $user->can('update ErrorIntegrationState');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ErrorIntegrationState $errorIntegrationState): bool
    {
        return $user->can('delete ErrorIntegrationState');
    }
}
