<?php

namespace App\Policies;

use App\Models\SelectOptions;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SelectOptionsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any SelectOptions');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SelectOptions $selectOptions): bool
    {
        return $user->can('view SelectOptions');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create SelectOptions');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SelectOptions $selectOptions): bool
    {
        return $user->can('update SelectOptions');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SelectOptions $selectOptions): bool
    {
        return $user->can('delete SelectOptions');
    }
}
