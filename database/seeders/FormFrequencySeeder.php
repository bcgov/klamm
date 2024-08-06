<?php

namespace Database\Seeders;

use App\Models\FormFrequency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormFrequencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormFrequency::create(['name' => 'Annually']);
        FormFrequency::create(['name' => 'Quarterly']);
        FormFrequency::create(['name' => 'Monthly']);
        FormFrequency::create(['name' => 'Weekly or more']);
    }
}
