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
        $tags = [
            'BCMailPlus',
            'MySS',
            'CourtForm',
            'ICFS',
            'DCV',
            'JAWS',
            'MIS',
            'ServiceCanada',
            'storeXML',
            'Digital Signature',
            'migration2025',
            'CHEFSCandidate',
        ];

        foreach ($tags as $tag) {
            FormTag::firstOrCreate(['name' => $tag]);
        }
    }
}
