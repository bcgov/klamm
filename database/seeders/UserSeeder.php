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
                'password' => 'thisisunsafe',
            ],
            [
                'name' => 'Robert',
                'email' => 'robert.seib@gov.bc.ca',
                'password' => 'thisisunsafe',
            ],
            [
                'name' => 'Bojan',
                'email' => 'bojan.zimonja@gov.bc.ca',
                'password' => 'thisisunsafe',
            ],
            [
                'name' => 'Will',
                'email' => 'will.kiiskila@gov.bc.ca',
                'password' => 'thisisunsafe',
            ],
            [
                'name' => 'Olga',
                'email' => 'olga.jubran@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Greg',
                'email' => 'greg.frog@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Sara',
                'email' => 'sara.bose@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Dana',
                'email' => 'dana.jensen@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Jeff',
                'email' => 'jeff.dorion@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Sofia',
                'email' => 'sofia.blaunshteyn@gov.bc.ca',
                'password' => 'klamm',
            ],
            [

                'name' => 'Tim',
                'email' => 'tim.vanderwekken@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Tekarra',
                'email' => 'tekarra.wilkinson@gov.bc.ca',
                'password' => 'klamm',
            ],
            [
                'name' => 'Siddharth',
                'email' => 'siddharth.sinha@gov.bc.ca',
                'password' => 'klamm',

            ],
            [
                'name' => 'Saranya',
                'email' => 'saranya.viswam@gov.bc.ca',
                'password' => 'thisisunsafe',
            ]

        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->name = $userData['name'];
            $user->email = $userData['email'];
            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->password = Hash::make($userData['password']);
            $user->save();
        }
    }
}
