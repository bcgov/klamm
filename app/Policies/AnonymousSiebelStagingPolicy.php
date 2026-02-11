<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousSiebelStaging;
use App\Models\User;

class AnonymousSiebelStagingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousSiebelStaging');
    }

    public function view(User $user, AnonymousSiebelStaging $anonymousSiebelStaging): bool
    {
        return $user->can('view AnonymousSiebelStaging');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousSiebelStaging');
    }

    public function update(User $user, AnonymousSiebelStaging $anonymousSiebelStaging): bool
    {
        return $user->can('update AnonymousSiebelStaging');
    }

    public function delete(User $user, AnonymousSiebelStaging $anonymousSiebelStaging): bool
    {
        return $user->can('delete AnonymousSiebelStaging');
    }
}
