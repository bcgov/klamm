<?php

namespace Database\Seeders;

use App\Models\BoundarySystemModeOfTransfer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemModeOfTransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemModeOfTransfer::create(['name' => 'AppGate']);
        BoundarySystemModeOfTransfer::create(['name' => 'Batch']);
        BoundarySystemModeOfTransfer::create(['name' => 'Real Time Sync​']);
    }
}
