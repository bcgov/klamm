<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\User;

class AnonymizationMethodsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymizationMethods');
    }

    public function view(User $user, AnonymizationMethods $anonymizationMethods): bool
    {
        return $user->can('view AnonymizationMethods');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymizationMethods');
    }

    public function update(User $user, AnonymizationMethods $anonymizationMethods): bool
    {
        return $user->can('update AnonymizationMethods');
    }

    public function delete(User $user, AnonymizationMethods $anonymizationMethods): bool
    {
        return $user->can('delete AnonymizationMethods');
    }
}
