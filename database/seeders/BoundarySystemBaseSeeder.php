<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BoundarySystemBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            BoundarySystemContactSeeder::class,
            BoundarySystemSeeder::class,
            BoundarySystemTagSeeder::class,
            BoundarySystemInterfaceSeeder::class,
        ]);
    }
}
