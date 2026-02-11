<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymizationColumnTag;
use App\Models\User;

class AnonymizationColumnTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymizationColumnTag');
    }

    public function view(User $user, AnonymizationColumnTag $anonymizationColumnTag): bool
    {
        return $user->can('view AnonymizationColumnTag');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymizationColumnTag');
    }

    public function update(User $user, AnonymizationColumnTag $anonymizationColumnTag): bool
    {
        return $user->can('update AnonymizationColumnTag');
    }

    public function delete(User $user, AnonymizationColumnTag $anonymizationColumnTag): bool
    {
        return $user->can('delete AnonymizationColumnTag');
    }
}
