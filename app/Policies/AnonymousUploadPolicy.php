<?php

namespace App\Policies;

use App\Models\Anonymizer\AnonymousUpload;
use App\Models\User;

class AnonymousUploadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any AnonymousUpload');
    }

    public function view(User $user, AnonymousUpload $anonymousUpload): bool
    {
        return $user->can('view AnonymousUpload');
    }

    public function create(User $user): bool
    {
        return $user->can('create AnonymousUpload');
    }

    public function update(User $user, AnonymousUpload $anonymousUpload): bool
    {
        return $user->can('update AnonymousUpload');
    }

    public function delete(User $user, AnonymousUpload $anonymousUpload): bool
    {
        return $user->can('delete AnonymousUpload');
    }
}
