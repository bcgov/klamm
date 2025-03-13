<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFileFieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemFileFieldTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemFileFieldType::create(['name' => 'Numeric']);
        BoundarySystemFileFieldType::create(['name' => 'Alphanumeric']);
    }
}
