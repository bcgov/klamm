<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignUserRole extends Command
{
    protected $signature = 'assign-user-role';
    protected $description = 'Assign roles to an existing user';

    public function handle()
    {
        $email = $this->ask('What is the user\'s email?');

        // Find the user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error('User not found.');
            return;
        }

        // Fetch all available roles
        $availableRoles = Role::pluck('name')->toArray();
        if (empty($availableRoles)) {
            $this->info('No roles available to assign.');
            return;
        }

        $roles = $this->choice(
            'Select roles for the user (comma-separated for multiple roles)',
            $availableRoles,
            null,
            null,
            true // Allow multiple selections
        );

        // Assign roles to the user, overwriting existing roles
        $user->syncRoles($roles);
        $this->info('Roles assigned successfully!');
    }
}
