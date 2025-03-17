<?php

namespace App\Policies;

use App\Models\ReportBusinessArea;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportBusinessAreaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ReportBusinessArea');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReportBusinessArea $reportBusiness): bool
    {
        return $user->can('view ReportBusinessArea');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ReportBusinessArea');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportBusinessArea $reportBusiness): bool
    {
        return $user->can('update ReportBusinessArea');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportBusinessArea $reportBusiness): bool
    {
        return $user->can('delete ReportBusinessArea');
    }
}
