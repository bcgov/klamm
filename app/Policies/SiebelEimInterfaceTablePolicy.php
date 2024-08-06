<?php

namespace App\Policies;

use App\Models\SiebelEimInterfaceTable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelEimInterfaceTablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelEimInterfaceTable');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelEimInterfaceTable $siebelEimInterfaceTable): bool
    {
        return $user->can('view SiebelEimInterfaceTable');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelEimInterfaceTable');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelEimInterfaceTable $siebelEimInterfaceTable): bool
    {
        return $user->can('update SiebelEimInterfaceTable');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelEimInterfaceTable $siebelEimInterfaceTable): bool
    {
        return $user->can('delete SiebelEimInterfaceTable');
    }
}
