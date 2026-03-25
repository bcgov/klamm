<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErrorSource;

class ErrorSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            'ICM',
            'CFMS',
            'PBC',
            'Successor System',
        ];

        foreach ($sources as $name) {
            ErrorSource::firstOrCreate(['name' => $name]);
        }
    }
}
