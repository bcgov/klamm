<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\User;

class AnonymizationJobsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymizationJobs');
    }

    public function view(User $user, AnonymizationJobs $anonymizationJobs): bool
    {
        return $user->can('view AnonymizationJobs');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymizationJobs');
    }

    public function update(User $user, AnonymizationJobs $anonymizationJobs): bool
    {
        return $user->can('update AnonymizationJobs');
    }

    public function delete(User $user, AnonymizationJobs $anonymizationJobs): bool
    {
        return $user->can('delete AnonymizationJobs');
    }
}
