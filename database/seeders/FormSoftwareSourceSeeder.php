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
            'Livecycle',
            'Adobe Acrobat',
            'Orbeon',
            'Microsoft Word',
            'Microsoft PowerPoint',
            'Klamm',
        ];

        foreach ($softwareSources as $name) {
            FormSoftwareSource::firstOrCreate(['name' => $name]);
        }
    }
}
