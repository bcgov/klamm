<?php

namespace Database\Seeders;

use App\Models\ErrorEntity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
