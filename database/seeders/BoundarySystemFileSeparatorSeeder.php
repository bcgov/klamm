<?php

namespace Database\Seeders;

use App\Models\BoundarySystemFileSeparator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoundarySystemFileSeparatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BoundarySystemFileSeparator::create(['separator' => ' ', 'description' => 'space']);
        BoundarySystemFileSeparator::create(['separator' => '/t', 'description' => 'tab']);
        BoundarySystemFileSeparator::create(['separator' => '||', 'description' => 'double pipe']);
        BoundarySystemFileSeparator::create(['separator' => '/n', 'description' => 'new line']);
    }
}
