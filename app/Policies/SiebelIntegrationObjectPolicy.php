<?php

namespace App\Policies;

use App\Models\SiebelIntegrationObject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelIntegrationObjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelIntegrationObject');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelIntegrationObject $siebelIntegrationObject): bool
    {
        return $user->can('view SiebelIntegrationObject');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelIntegrationObject');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelIntegrationObject $siebelIntegrationObject): bool
    {
        return $user->can('update SiebelIntegrationObject');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelIntegrationObject $siebelIntegrationObject): bool
    {
        return $user->can('delete SiebelIntegrationObject');
    }
}
