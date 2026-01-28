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
        $classifications = [
            'Public',
            'Protected A',
            'Protected B',
            'Protected C',
        ];

        foreach ($classifications as $name) {
            SecurityClassification::firstOrCreate(['name' => $name]);
        }
    }
}
