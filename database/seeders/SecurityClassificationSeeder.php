<?php

namespace Database\Seeders;

use App\Models\SecurityClassification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SecurityClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SecurityClassification::create(['name' => 'Low Sensitivity']);
        SecurityClassification::create(['name' => 'High Sensitivity']);
    }
}
