<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\User;

class AnonymousSiebelColumnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelColumn');
    }

    public function view(User $user, AnonymousSiebelColumn $anonymousSiebelColumn): bool
    {
        return $user->can('view AnonymousSiebelColumn');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelColumn');
    }

    public function update(User $user, AnonymousSiebelColumn $anonymousSiebelColumn): bool
    {
        return $user->can('update AnonymousSiebelColumn');
    }

    public function delete(User $user, AnonymousSiebelColumn $anonymousSiebelColumn): bool
    {
        return $user->can('delete AnonymousSiebelColumn');
    }
}
