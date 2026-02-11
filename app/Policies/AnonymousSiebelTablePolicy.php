<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\User;

class AnonymousSiebelTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelTable');
    }

    public function view(User $user, AnonymousSiebelTable $anonymousSiebelTable): bool
    {
        return $user->can('view AnonymousSiebelTable');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelTable');
    }

    public function update(User $user, AnonymousSiebelTable $anonymousSiebelTable): bool
    {
        return $user->can('update AnonymousSiebelTable');
    }

    public function delete(User $user, AnonymousSiebelTable $anonymousSiebelTable): bool
    {
        return $user->can('delete AnonymousSiebelTable');
    }
}
