<?php

namespace Database\Factories;

use App\Models\BREDataType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\BREField;
use App\Models\ICMCDWField;
use App\Models\BRERule;
use App\Models\BREFieldGroup;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BREField>
 */
class BREFieldFactory extends Factory
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
            'help_text' => $this->faker->sentence(),
            'data_type_id' => BREDataType::factory()->create()->id,
            'description' => $this->faker->sentence(),
            'icmcdw_fields' => ICMCDWField::factory()->count(3)->make()->toArray(),
            'rule_inputs' => BRERule::factory()->count(3)->make()->toArray(),
            'rule_outputs' => BRERule::factory()->count(3)->make()->toArray(),
            'field_groups' => BREFieldGroup::factory()->count(3)->make()->toArray(),
        ];
    }
}
