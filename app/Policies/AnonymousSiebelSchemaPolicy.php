<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\User;

class AnonymousSiebelSchemaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelSchema');
    }

    public function view(User $user, AnonymousSiebelSchema $anonymousSiebelSchema): bool
    {
        return $user->can('view AnonymousSiebelSchema');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelSchema');
    }

    public function update(User $user, AnonymousSiebelSchema $anonymousSiebelSchema): bool
    {
        return $user->can('update AnonymousSiebelSchema');
    }

    public function delete(User $user, AnonymousSiebelSchema $anonymousSiebelSchema): bool
    {
        return $user->can('delete AnonymousSiebelSchema');
    }
}
