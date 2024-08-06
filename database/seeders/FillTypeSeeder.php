<?php

namespace Database\Seeders;

use App\Models\FillType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FillTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FillType::create(['name' => 'Static']);
        FillType::create(['name' => 'Dynamic']);
    }
}
