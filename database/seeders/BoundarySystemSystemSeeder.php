<?php

namespace Database\Seeders;

use App\Models\BoundarySystemSystem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemSystem::create(['name' => 'ICM']);
        BoundarySystemSystem::create(['name' => 'MIS']);
        BoundarySystemSystem::create(['name' => 'CAS​']);
        BoundarySystemSystem::create(['name' => 'Service Canada​']);
        BoundarySystemSystem::create(['name' => 'BC Mail Plus​']);
        BoundarySystemSystem::create(['name' => 'Provincial Treasury​']);
    }
}
