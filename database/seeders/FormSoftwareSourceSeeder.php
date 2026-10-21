<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormSoftwareSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormSoftwareSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $softwareSources = [
            ['name' => 'Adobe Livecycle', 'description' => ''],
            ['name' => 'Adobe Acrobat', 'description' => ''],
            ['name' => 'Orbeon', 'description' => ''],
            ['name' => 'Microsoft Word', 'description' => ''],
            ['name' => 'Microsoft PowerPoint', 'description' => ''],
            ['name' => 'Klamm', 'description' => 'Form is built in FormFoundry'],
            ['name' => 'CHEFS', 'description' => ''],
            ['name' => 'Microsoft Excel', 'description' => ''],
            ['name' => 'Infopath', 'description' => ''],
            ['name' => 'Adobe', 'description' => 'Adobe PDF form'],
            ['name' => 'Unknown', 'description' => 'We have no way to verify the software source for this document'],
        ];

        foreach ($softwareSources as $source) {
            FormSoftwareSource::firstOrCreate($source);
        }
    }
}
