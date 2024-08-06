<?php

namespace App\Policies;

use App\Models\SiebelWebPage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelWebPagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelWebPage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelWebPage $siebelWebPage): bool
    {
        return $user->can('view SiebelWebPage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelWebPage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelWebPage $siebelWebPage): bool
    {
        return $user->can('update SiebelWebPage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelWebPage $siebelWebPage): bool
    {
        return $user->can('delete SiebelWebPage');
    }
}
