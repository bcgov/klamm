<?php

namespace App\Policies;

use App\Models\ICMCDWField;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BREICMCDWFieldPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any BREICMCDWField');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ICMCDWField $iCMCDWField): bool
    {
        return $user->can('view BREICMCDWField');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BREICMCDWField');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ICMCDWField $iCMCDWField): bool
    {
        return $user->can('update BREICMCDWField');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ICMCDWField $iCMCDWField): bool
    {
        return $user->can('delete BREICMCDWField');
    }
}
