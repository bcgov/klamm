<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormTag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormTag::truncate();

        $tags = [
            'BCMailPlus',
            'MySS',
            'CourtForm',
            'ICFS',
            'DCV',
            'JAWS',
            'MIS',
            'ServiceCanada',
        ];

        foreach ($tags as $tag) {
            FormTag::create(['name' => $tag]);
        }
    }
}
