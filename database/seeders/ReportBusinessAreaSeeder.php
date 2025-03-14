<?php

namespace Database\Seeders;

use App\Models\ReportBusinessArea;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportBusinessAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessAreas = [
            'Business Intelligence',
            'CS Focus Team',
            'FASB AR & Debt',
            'FASB Banking',
            'FASB Budget',
            'ISD Data Stewards',
            'Local Offices',
            'PLMS',
            'Sponsorship',
        ];

        foreach ($businessAreas as $area) {
            ReportBusinessArea::create(['name' => $area]);
        }
    }
}
