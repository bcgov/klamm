<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BREBasicSeeder extends Seeder
{
    /**
     * Run the database seeders for the BRE.
     * Intended to be used as a single entry point for seeding the BRE database.
     * This provides the most basic patterns for interacting with the Business Rules Engine without populating the db with fields or rules.
     */
    public function run(): void
    {
        $this->call([
            BREValueTypeSeeder::class,
            BREDataTypeSeeder::class,
            BREValidationTypeSeeder::class,
            BREDataValidationSeeder::class,
        ]);
    }
}
