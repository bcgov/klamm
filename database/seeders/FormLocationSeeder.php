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
            ['name' => 'ICM', 'description' => ''],
            ['name' => 'iConnect', 'description' => 'MCFD Intranet'],
            ['name' => 'Resource Finder', 'description' => 'SDPR document store and standard operating procedures'],
            ['name' => 'MySS', 'description' => 'Form exists in MySelfServe portal as a template'],
            ['name' => 'Loop', 'description' => 'SDPR Intranet'],
            ['name' => 'BC Gov Web', 'description' => 'Hosted on the BC Government Website'],
            ['name' => 'PPM', 'description' => 'Policy and Procedures Manual'],
            ['name' => 'ConECCt', 'description' => 'ECC (Education and Childcare) Intranet'],
            ['name' => 'FormFoundry', 'description' => ''],
            ['name' => 'MyFS', 'description' => 'My Family Services Portal, used by MCFD Autism Services and ECC Child Care Services'],
            ['name' => 'OES', 'description' => 'Online Employment Services portal'],
            ['name' => 'WorkBC Extranet', 'description' => ''],
            ['name' => 'Caregiver Portal', 'description' => ''],
            ['name' => 'Unknown', 'description' => 'This form cannot be located'],
            ['name' => 'CHEFS', 'description' => ''],
        ];

        foreach ($locations as $location) {
            FormLocation::firstOrCreate($location);
        }
    }
}
