<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class FormLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            'ICM Target',
            'ICM Dev',
            'ICM Test',
            'iConnect',
            'Resource Finder',
            'MySelfServe',
            'Loop',
        ];

        foreach ($locations as $name) {
            FormLocation::firstOrCreate(['name' => $name]);
        }
    }
}
