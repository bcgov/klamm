<?php

namespace Database\Factories;

use App\Models\BREField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BREFieldGroup>
 */
class BREFieldGroupFactory extends Factory
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
            'bre_fields' => BREField::factory()->count(3)->make()->toArray(),
        ];
    }
}
