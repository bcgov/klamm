<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $users = [
            ['name' => 'Jeremy', 'email' => 'jeremy.vernon@gov.bc.ca'],
            ['name' => 'Bojan', 'email' => 'bojan.zimonja@gov.bc.ca'],
            ['name' => 'Will', 'email' => 'will.kiiskila@gov.bc.ca'],
            ['name' => 'Tim', 'email' => 'tim.vanderwekken@gov.bc.ca'],
            ['name' => 'Saranya', 'email' => 'saranya.viswam@gov.bc.ca'],
            ['name' => 'Bryson', 'email' => 'bryson.best@gov.bc.ca'],
            ['name' => 'David', 'email' => 'david.okulski@gov.bc.ca'],
            ['name' => 'Joh', 'email' => 'johtaro.yoshida@gov.bc.ca'],
            ['name' => 'Josh', 'email' => 'joshua.larouche@gov.bc.ca'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('klamm'),
                ]
            );

            $user->syncRoles([$adminRole]);
        }
    }
}
