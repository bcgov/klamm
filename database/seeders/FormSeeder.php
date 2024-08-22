<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FormsTableSeeder::class,
            FormFormLocationTableSeeder::class,
            FormFormTagsTableSeeder::class,
            FormSoftwareSourceFormTableSeeder::class,
            FormUserTypeTableSeeder::class,
            FormWorkbenchPathsTableSeeder::class,
            FormBusinessAreaTableSeeder::class,
        ]);
    }
}
