<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DataGroup;

class DataGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table(table: 'data_groups')->delete();

        $dataGroups = [
            'Case Address',
            'Case Benefit Plan',
            'Case Benefit Recall',
            'Case Client Bank Information',
            'Case Contact Eligibility',
            'Case Expenses',
            'Case IA Information',
            'Case Information',
            'Case Service Order',
            'Case Signal Cheque',
            'Contact Case Assets',
            'Contact CPA',
            'Contact Immigration',
            'Varies',
        ];

        foreach ($dataGroups as $dataGroup) {
            DataGroup::firstOrCreate(['name' => $dataGroup]);
        }
    }
}
