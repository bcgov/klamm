<?php

namespace Database\Seeders;

use App\Models\BRERule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BRERuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $breRules = [
            ['name' => 'dasupport', 'label' => 'Disability Assistance Support', 'description' => 'Calculates the disability assistance support.'],
            ['name' => 'iaSupport', 'label' => 'Income Assistance Support', 'description' => 'Calculates the income assistance support.'],
            ['name' => 'ccbEligibleChidren', 'label' => 'Children Count', 'description' => 'Calculates the number of eligible children eligible for CCB or support in lieu.'],
            ['name' => 'ccbInLieu', 'label' => 'CCB In Lieu', 'description' => 'Calculates the amount to provide for CCB in lieu eligibility.'],
            ['name' => 'ccbCalculation', 'label' => 'CCB Calculation', 'description' => 'Calculates the amount to provide as a topup for eligible families.'],
            ['name' => 'finalCCBCalculation', 'label' => 'Final CCB Calculation', 'description' => 'Calculates the final amount to provide for eligible families.'],
        ];

        foreach ($breRules as $breRule) {
            BRERule::create($breRule);
        }
    }
}
