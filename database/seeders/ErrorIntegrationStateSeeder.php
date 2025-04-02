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
        ErrorIntegrationState::create(['name' => 'Error']);
        ErrorIntegrationState::create(['name' => 'Not Integrated']);
        ErrorIntegrationState::create(['name' => 'Pending']);
        ErrorIntegrationState::create(['name' => 'Processing']);
        ErrorIntegrationState::create(['name' => 'Queued']);
        ErrorIntegrationState::create(['name' => 'Synced']);
    }
}
