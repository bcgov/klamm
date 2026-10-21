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
        $fillTypes = [
            'Static',
            'Dynamic',
        ];

        foreach ($fillTypes as $name) {
            FillType::firstOrCreate(['name' => $name]);
        }
    }
}
