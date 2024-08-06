<?php

namespace App\Policies;

use App\Models\SiebelWorkflowProcess;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SiebelWorkflowProcessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SiebelWorkflowProcess');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SiebelWorkflowProcess $siebelWorkflowProcess): bool
    {
        return $user->can('view SiebelWorkflowProcess');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SiebelWorkflowProcess');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SiebelWorkflowProcess $siebelWorkflowProcess): bool
    {
        return $user->can('update SiebelWorkflowProcess');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SiebelWorkflowProcess $siebelWorkflowProcess): bool
    {
        return $user->can('delete SiebelWorkflowProcess');
    }
}
