<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemMessageSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ErrorEntitySeeder::class,
            ErrorDataGroupSeeder::class,
            ErrorIntegrationStateSeeder::class,
            ErrorActorSeeder::class,
            ErrorSourceSeeder::class,
            SystemMessagesTableSeeder::class,
        ]);
    }
}
