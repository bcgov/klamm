<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelDataType;
use App\Models\User;

class AnonymousSiebelDataTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelDataType');
    }

    public function view(User $user, AnonymousSiebelDataType $anonymousSiebelDataType): bool
    {
        return $user->can('view AnonymousSiebelDataType');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelDataType');
    }

    public function update(User $user, AnonymousSiebelDataType $anonymousSiebelDataType): bool
    {
        return $user->can('update AnonymousSiebelDataType');
    }

    public function delete(User $user, AnonymousSiebelDataType $anonymousSiebelDataType): bool
    {
        return $user->can('delete AnonymousSiebelDataType');
    }
}
