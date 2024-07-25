<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BRESeeder extends Seeder
{
    /**
     * Run the database seeders for the BRE.
     */
    public function run(): void
    {
        $this->call([
            BREValueTypeSeeder::class,
            BREDataTypeSeeder::class,
            BREFieldSeeder::class,
            BREFieldGroupSeeder::class,
            BRERuleSeeder::class,
        ]);
    }
}
