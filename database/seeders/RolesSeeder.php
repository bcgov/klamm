<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RolesSeeder extends Seeder
{
    public function run()
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'bre']);
        Role::create(['name' => 'fodig']);
        Role::create(['name' => 'forms']);
        Role::create(['name' => 'user']);
        Role::create(['name' => 'form-developer']);
    }
}
