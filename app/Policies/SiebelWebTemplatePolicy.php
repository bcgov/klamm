<?php

namespace App\Policies;

use App\Models\SiebelWebTemplate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelWebTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelWebTemplate');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelWebTemplate $siebelWebTemplate): bool
    {
        return $user->can('view SiebelWebTemplate');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelWebTemplate');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelWebTemplate $siebelWebTemplate): bool
    {
        return $user->can('update SiebelWebTemplate');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelWebTemplate $siebelWebTemplate): bool
    {
        return $user->can('delete SiebelWebTemplate');
    }
}
