<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymizationPackage;
use App\Models\User;

class AnonymizationPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymizationPackage');
    }

    public function view(User $user, AnonymizationPackage $anonymizationPackage): bool
    {
        return $user->can('view AnonymizationPackage');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymizationPackage');
    }

    public function update(User $user, AnonymizationPackage $anonymizationPackage): bool
    {
        return $user->can('update AnonymizationPackage');
    }

    public function delete(User $user, AnonymizationPackage $anonymizationPackage): bool
    {
        return $user->can('delete AnonymizationPackage');
    }
}
