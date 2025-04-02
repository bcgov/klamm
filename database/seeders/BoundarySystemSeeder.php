<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFileFieldMap;
use App\Models\BoundarySystemFileFieldType;
use App\Models\BoundarySystemFileSeparator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemSeeder extends Seeder
{
    /**
     * Run the database seeders for the Boundary Systems.
     */
    public function run(): void
    {
        $this->call([
            BoundarySystemProcessSeeder::class,
            BoundarySystemFileFormatSeeder::class,
            BoundarySystemFrequencySeeder::class,
            BoundarySystemModeOfTransferSeeder::class,
            BoundarySystemSystemSeeder::class,
            BoundarySystemFileFieldMapSectionSeeder::class,
            BoundarySystemFileFieldTypeSeeder::class,
            BoundarySystemFileSeparatorSeeder::class,
        ]);
    }
}
