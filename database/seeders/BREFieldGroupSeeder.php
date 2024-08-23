<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BREFieldGroup;

class BREFieldGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $breFieldGroups = [
            ['name' => 'contact', 'label' => 'Contact', 'description' => 'Inputs and outputs for the contact.'],
            ['name' => 'benefitPlan', 'label' => 'Benefit Plan', 'description' => 'Inputs and outputs for the benefit plan.'],
            ['name' => 'case', 'label' => 'Case', 'description' => 'Inputs and outputs for the case.'],
        ];

        foreach ($breFieldGroups as $breFieldGroup) {
            BREFieldGroup::create($breFieldGroup);
        }
    }
}
