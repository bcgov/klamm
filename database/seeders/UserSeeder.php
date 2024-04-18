<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        $jeremy = new User();
        $jeremy->name = 'Jeremy';
        $jeremy->email = 'jeremy.vernon@gov.bc.ca';
        $jeremy->email_verified_at = date('Y-m-d H:i:s');
        $jeremy->password = Hash::make('thisisunsafe');
        $jeremy->save();

        $rob = new User();
        $rob->name = 'Robert';
        $rob->email = 'robert.seib@gov.bc.ca';
        $rob->email_verified_at = date('Y-m-d H:i:s');
        $rob->password = Hash::make('thisisunsafe');
        $rob->save();

        $bojan = new User();
        $bojan->name = 'Bojan';
        $bojan->email = 'bojan.zimonja@gov.bc.ca';
        $bojan->email_verified_at = date('Y-m-d H:i:s');
        $bojan->password = Hash::make('thisisunsafe');
        $bojan->save();

    }
}
