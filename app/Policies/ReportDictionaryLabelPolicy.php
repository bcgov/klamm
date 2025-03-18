<?php

namespace App\Policies;

use App\Models\ReportDictionaryLabel;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportDictionaryLabelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ReportDictionaryLabel');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReportDictionaryLabel $reportDictionaryLabel): bool
    {
        return $user->can('view ReportDictionaryLabel');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create ReportDictionaryLabel');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportDictionaryLabel $reportDictionaryLabel): bool
    {
        return $user->can('update ReportDictionaryLabel');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportDictionaryLabel $reportDictionaryLabel): bool
    {
        return $user->can('delete ReportDictionaryLabel');
    }
}
