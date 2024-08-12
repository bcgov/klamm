<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Dotenv\Parser\Value;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MinistrySeeder::class,
            ValueTypeSeeder::class,
            RenderedFormSeeder::class,
            DataTypeSeeder::class,
            FormFieldSeeder::class,
            RolesSeeder::class,
            PermissionsSeeder::class,
            SelectOptionsSeeder::class,
            BusinessAreaSeeder::class,
            FillTypeSeeder::class,
            FormFrequencySeeder::class,
            FormLocationSeeder::class,
            FormReachSeeder::class,
            FormRepositorySeeder::class,
            FormSoftwareSourceSeeder::class,
            UserTypeSeeder::class,
            FormTagSeeder::class,
            FormsTableSeeder::class,
            FormBusinessAreaTableSeeder::class,
        ]);
    }
}
