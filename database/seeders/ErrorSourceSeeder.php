<?php

namespace Database\Seeders;

use App\Models\ErrorSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
