<?php

namespace App\Policies;

use App\Models\RenderedForm;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RenderedFormPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any RenderedForm');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RenderedForm $renderedForm): bool
    {
        return $user->can('view RenderedForm');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create RenderedForm');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RenderedForm $renderedForm): bool
    {
        return $user->can('update RenderedForm');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RenderedForm $renderedForm): bool
    {
        return $user->can('delete RenderedForm');
    }
}
