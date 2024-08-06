<?php

namespace App\Policies;

use App\Models\FillType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FillTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any FillType');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FillType $fillType): bool
    {
        return $user->can('view FillType');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FillType');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FillType $fillType): bool
    {
        return $user->can('update FillType');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FillType $fillType): bool
    {
        return $user->can('delete FillType');
    }
}
