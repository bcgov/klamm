<?php

namespace Database\Seeders\Anonymization;

use Illuminate\Database\Seeder;

/**
 * Seeds the Faker-backed anonymization catalog only.
 *
 * This is the lean entry point when you want:
 * - package metadata imported from generated package SQL, and
 * - anonymization methods whose SQL calls those installed packages.
 *
 * Usage:
 *   sail artisan db:seed --class=AnonymizationFakerSeeder
 */
class AnonymizationFakerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AnonymizationFakerPackageSeeder::class,
            AnonymizationFakerLookupMethodSeeder::class,
        ]);
    }
}
