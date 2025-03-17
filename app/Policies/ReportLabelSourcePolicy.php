<?php

namespace App\Policies;

use App\Models\ReportLabelSource;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportLabelSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ReportLabelSource');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReportLabelSource $reportLabelSource): bool
    {
        return $user->can('view ReportLabelSource');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ReportLabelSource');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportLabelSource $reportLabelSource): bool
    {
        return $user->can('update ReportLabelSource');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportLabelSource $reportLabelSource): bool
    {
        return $user->can('delete ReportLabelSource');
    }
}
