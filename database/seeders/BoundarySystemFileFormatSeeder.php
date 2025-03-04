<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFileFormat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemFileFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemFileFormat::create(['name' => 'Flat file (.txt)']);
        BoundarySystemFileFormat::create(['name' => 'Macro Excel Spreadsheet']);
        BoundarySystemFileFormat::create(['name' => 'MVS (mainframe) files​']);
        BoundarySystemFileFormat::create(['name' => '​Mainframe (MVS) mailbox']);
    }
}
