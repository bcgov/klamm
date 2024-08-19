<?php

namespace Database\Factories;

use App\Models\BREValueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BREDataType>
 */
class BREDataTypeFactory extends Factory
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
            'value_type_id' => BREValueType::factory()->create()->id,
            'short_description' => $this->faker->sentence(),
            'long_description' => $this->faker->sentence(),
            'validation' => $this->faker->sentence(),
        ];
    }
}
