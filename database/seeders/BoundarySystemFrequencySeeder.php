<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFrequency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemFrequencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemFrequency::create(['name' => 'Daily']);
        BoundarySystemFrequency::create(['name' => 'Weekly']);
        BoundarySystemFrequency::create(['name' => 'Monthly']);
        BoundarySystemFrequency::create(['name' => 'Yearly']);
    }
}
