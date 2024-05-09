<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'create-admin-user';
    protected $description = 'Creates a new admin user';

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
            $this->info('Admin user created successfully!');
        } else {
            $this->error('Failed to create admin user.');
        }
    }
}