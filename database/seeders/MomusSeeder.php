<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MomusSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Increase memory limit for ICM data
        ini_set('memory_limit', '1024M');

        //FYI - SiebelClasses & SiebelTables have FK to themseleves. So their seeders work differently.
        // First the seeder loads with no FK and then a script is executed from those seeders and that will map the FK.
        $this->call([
            SiebelProjectsTableSeeder::class,
            SiebelClassesTableSeederWithoutFK::class,
            SiebelTablesTableSeeder::class,
            SiebelBusinessComponentsTableSeeder::class,
            SiebelBusinessObjectsTableSeeder::class,
            SiebelViewsTableSeeder::class,
            SiebelScreensTableSeeder::class,
            SiebelApplicationsTableSeeder::class,
            SiebelAppletsTableSeeder::class,
            SiebelIntegrationObjectsTableSeeder::class,
            SiebelWebTemplatesTableSeeder::class,
            SiebelWebPagesTableSeeder::class,
            SiebelLinksTableSeeder::class,
            SiebelBusinessServicesTableSeeder::class,
            SiebelEimInterfaceTablesTableSeeder::class,
            SiebelValuesTableSeeder::class,
            SiebelWorkflowProcessesTableSeeder::class
        ]);
    }
}
