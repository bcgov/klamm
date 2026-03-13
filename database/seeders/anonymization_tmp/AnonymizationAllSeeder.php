<?php

namespace Database\Seeders\Anonymization;

use Illuminate\Database\Seeder;

/**
 * Master anonymization seeder.
 * Uses the full method catalog and seeds demo anonymization job metadata with generated SQL.
 * Run this to get all anonymization seed data in place for testing and development.
 * For more targeted seeding, use the individual seeders.
 *
 * Runs anonymization seeders in dependency order:
 * 1) Faker package metadata
 * 2) Method
 * 3) Rule catalog and default rule->method mappings
 * 4) Demo anonymization job metadata and generated SQL
 *
 * Usage:
 *   sail artisan db:seed --class="Database\\Seeders\\Anonymization\\AnonymizationAllSeeder"
 *
 */
class AnonymizationAllSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AnonymizationFakerPackageSeeder::class,
            AnonymizationMethodSeeder::class,
            AnonymizationRuleMethodSeeder::class,
            // Demo anonymization job
            AnonymizationAnonymousTestJobSeeder::class,
        ]);
    }
}
