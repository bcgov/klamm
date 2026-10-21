<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErrorEntity;

class ErrorEntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entities = [
            'Case',
            'Contact',
        ];

        foreach ($entities as $name) {
            ErrorEntity::firstOrCreate(['name' => $name]);
        }
    }
}
