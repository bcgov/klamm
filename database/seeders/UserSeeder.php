<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * $table->id();
     * $table->string('name');
     * $table->string('email')->unique();
     * $table->timestamp('email_verified_at')->nullable();
     * $table->string('password');
     * $table->rememberToken();
     * $table->timestamps();
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Jeremy',
                'email' => 'jeremy.vernon@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Bojan',
                'email' => 'bojan.zimonja@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Will',
                'email' => 'will.kiiskila@gov.bc.ca',
                'password' => 'klamm',
            ],
            [

                'name' => 'Tim',
                'email' => 'tim.vanderwekken@gov.bc.ca',
            ],
            [
                'name' => 'Saranya',
                'email' => 'saranya.viswam@gov.bc.ca',
            ],
            [
                'name' => 'Bryson',
                'email' => 'bryson.best@gov.bc.ca',
            ],
            [
                'name' => 'David',
                'email' => 'david.okulski@gov.bc.ca',
            ],

        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->name = $userData['name'];
            $user->email = $userData['email'];
            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->password = Hash::make('klamm');
            $user->save();
        }
    }
}
