<?php

namespace Database\Seeders;

use App\Models\ReportLabelSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportLabelSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reportDctionaryLabels = [
            'ICM',
            'MIS',
            'Report',
            'TBD'
        ];

        foreach ($reportDctionaryLabels as $reportDictionaryLabel) {
            ReportLabelSource::create(['name' => $reportDictionaryLabel]);
        }
    }
}
