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
        ErrorSource::create(['name' => 'ICM']);
        ErrorSource::create(['name' => 'CFMS']);
        ErrorSource::create(['name' => 'PBC']);
        ErrorSource::create(['name' => 'Successor System']);
    }
}
