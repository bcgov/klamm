<?php

namespace Database\Seeders;

use App\Models\FormTag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormTag::create(['name' => 'King\'s Printer']);
        FormTag::create(['name' => 'BCMailPlus']);
        FormTag::create(['name' => 'Government of Canada']);
        FormTag::create(['name' => 'Specialized Governance: Specified']);
        FormTag::create(['name' => 'Specialized Governance: Prescribed']);
        FormTag::create(['name' => 'Distribution Centre Victoria']);
    }
}
