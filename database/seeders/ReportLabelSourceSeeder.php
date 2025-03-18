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
        $label = new ReportLabelSource();
        $label->name = 'ICM';
        $label->save();

        $label = new ReportLabelSource();
        $label->name = 'Financial Component';
        $label->save();

        $label = new ReportLabelSource();
        $label->name = 'MIS';
        $label->save();
    }
}
