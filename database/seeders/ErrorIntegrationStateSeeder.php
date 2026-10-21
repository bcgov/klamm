<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErrorIntegrationState;

class ErrorIntegrationStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            'Error',
            'Not Integrated',
            'Pending',
            'Processing',
            'Queued',
            'Synced',
        ];

        foreach ($states as $name) {
            ErrorIntegrationState::firstOrCreate(['name' => $name]);
        }
    }
}
