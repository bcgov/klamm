<?php

namespace App\Policies;

use App\Models\Anonymizer\ChangeTicket;
use App\Models\User;

class ChangeTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any ChangeTicket');
    }

    public function view(User $user, ChangeTicket $changeTicket): bool
    {
        return $user->can('view ChangeTicket');
    }

    public function create(User $user): bool
    {
        return $user->can('create ChangeTicket');
    }

    public function update(User $user, ChangeTicket $changeTicket): bool
    {
        return $user->can('update ChangeTicket');
    }

    public function delete(User $user, ChangeTicket $changeTicket): bool
    {
        return $user->can('delete ChangeTicket');
    }
}
