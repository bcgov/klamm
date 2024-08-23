<?php

namespace Database\Factories;

use App\Models\BREField;
use App\Models\ICMCDWField;
use App\Models\BRERule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BRERule>
 */
class BRERuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'label' => $this->faker->name(),
            'description' => $this->faker->sentence(),
            'internal_description' => $this->faker->sentence(),
            'related_icm_cdw_fields' => ICMCDWField::factory()->count(3)->make()->toArray(),
            'rule_inputs' => BREField::factory()->count(3)->make()->toArray(),
            'rule_outputs' => BREField::factory()->count(3)->make()->toArray(),
            'parent_rules' => BRERule::factory()->count(3)->make()->toArray(),
            'child_rules' => BRERule::factory()->count(3)->make()->toArray(),
            'icmcdw_fields' => ICMCDWField::factory()->count(3)->make()->toArray(),
        ];
    }
}
