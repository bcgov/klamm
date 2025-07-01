<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormReach;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormReachSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormReach::create(['name' => 'Less than 1000']);
        FormReach::create(['name' => '1000 to 5000']);
        FormReach::create(['name' => 'More than 5000']);
    }
}
