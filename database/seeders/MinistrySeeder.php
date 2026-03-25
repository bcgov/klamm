<?php

namespace Database\Seeders;

use App\Models\Ministry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class MinistrySeeder extends Seeder
{
    public function run(): void
    {
        $ministries = [
            'MCFD' => 'Ministry of Children and Family Development',
            'SDPR' => 'Ministry of Social Development and Poverty Reduction',
            'ECC' => 'Ministry of Education and Child Care',
            'FEDGOVBC' => 'Government of Canada and Province of British Columbia',
        ];

        foreach ($ministries as $shortName => $name) {
            Ministry::firstOrCreate(
                ['short_name' => $shortName],
                ['name' => $name]
            );
        }
    }
}
