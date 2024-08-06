<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateUser extends Command
{
    protected $signature = 'create-user';
    protected $description = 'Creates a new user';

    public function handle()
    {
        $name = $this->ask('What is the user\'s name?');
        $email = $this->ask('What is the user\'s email?');
        $password = $this->ask('What is the user\'s password?');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        if ($user) {
            $this->info('User created successfully!');

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

            // Assign roles to the user
            $user->syncRoles($roles);
            $this->info('Roles assigned successfully!');
        } else {
            $this->error('Failed to create user.');
        }
    }
}
