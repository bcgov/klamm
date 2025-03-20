<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFileFieldMapSection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemFileFieldMapSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemFileFieldMapSection::create(['name' => 'Header']);
        BoundarySystemFileFieldMapSection::create(['name' => 'Body']);
        BoundarySystemFileFieldMapSection::create(['name' => 'Footer']);
    }
}
