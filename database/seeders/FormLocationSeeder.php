<?php

namespace Database\Seeders;

use App\Models\FormLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormLocation::create(['name' => 'ICM Target']);
        FormLocation::create(['name' => 'ICM Dev']);
        FormLocation::create(['name' => 'ICM Test']);
        FormLocation::create(['name' => 'iConnect']);
        FormLocation::create(['name' => 'Resource Finder']);
        FormLocation::create(['name' => 'MySelfServe']);
        FormLocation::create(['name' => 'Loop']);
    }
}
