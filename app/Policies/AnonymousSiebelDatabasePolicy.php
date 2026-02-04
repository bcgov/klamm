<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\User;

class AnonymousSiebelDatabasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelDatabase');
    }

    public function view(User $user, AnonymousSiebelDatabase $anonymousSiebelDatabase): bool
    {
        return $user->can('view AnonymousSiebelDatabase');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelDatabase');
    }

    public function update(User $user, AnonymousSiebelDatabase $anonymousSiebelDatabase): bool
    {
        return $user->can('update AnonymousSiebelDatabase');
    }

    public function delete(User $user, AnonymousSiebelDatabase $anonymousSiebelDatabase): bool
    {
        return $user->can('delete AnonymousSiebelDatabase');
    }
}
