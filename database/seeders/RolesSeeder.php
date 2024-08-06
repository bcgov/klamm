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
        Role::create(['name' => 'bre-view-only']);
        Role::create(['name' => 'fodig-view-only']);
        Role::create(['name' => 'forms-view-only']);
    }
}
