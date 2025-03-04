<?php

namespace Database\Seeders;

use App\Models\BoundarySystemProcess;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemProcessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemProcess::create(['name' => 'Payments']);
        BoundarySystemProcess::create(['name' => 'CPP']);
        BoundarySystemProcess::create(['name' => 'BCEA']);
    }
}
