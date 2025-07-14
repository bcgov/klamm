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
        FormSoftwareSource::create(['name' => 'Livecycle']);
        FormSoftwareSource::create(['name' => 'Adobe Acrobat']);
        FormSoftwareSource::create(['name' => 'Orbeon']);
        FormSoftwareSource::create(['name' => 'Microsoft Word']);
        FormSoftwareSource::create(['name' => 'Microsoft PowerPoint']);
        FormSoftwareSource::create(['name' => 'Klamm']); // todo: change to new forms platform once named
    }
}
