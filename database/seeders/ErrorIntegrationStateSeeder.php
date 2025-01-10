<?php

namespace Database\Seeders;

use App\Models\ErrorIntegrationState;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
