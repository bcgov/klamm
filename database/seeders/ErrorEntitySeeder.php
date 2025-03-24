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
        ErrorEntity::create(['name' => 'Case']);
        ErrorEntity::create(['name' => 'Contact']);
    }
}
