<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormFrequency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class FormFrequencySeeder extends Seeder
{
    public function run(): void
    {
        $frequencies = [
            'Annually',
            'Quarterly',
            'Monthly',
            'Weekly or more',
        ];

        foreach ($frequencies as $name) {
            FormFrequency::firstOrCreate(['name' => $name]);
        }
    }
}
