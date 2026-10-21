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
        $reachCategories = [
            'Less than 1000',
            '1000 to 5000',
            'More than 5000',
        ];

        foreach ($reachCategories as $name) {
            FormReach::firstOrCreate(['name' => $name]);
        }
    }
}
