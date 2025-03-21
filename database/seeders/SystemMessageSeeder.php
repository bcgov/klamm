<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ErrorEntitySeeder::class,
            ErrorDataGroupSeeder::class,
            ErrorIntegrationStateSeeder::class,
            ErrorActorSeeder::class,
            ErrorSourceSeeder::class,
        ]);
    }
}
