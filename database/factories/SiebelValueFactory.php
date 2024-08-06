<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelValue;

class SiebelValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelValue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'inactive' => $this->faker->boolean(),
            'type' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'display_value' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'changed' => $this->faker->boolean(),
            'translate' => $this->faker->boolean(),
            'multilingual' => $this->faker->boolean(),
            'language_independent_code' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'parent_lic' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'high' => $this->faker->regexify('[A-Za-z0-9]{300}'),
            'low' => $this->faker->regexify('[A-Za-z0-9]{300}'),
            'order' => $this->faker->numberBetween(-10000, 10000),
            'active' => $this->faker->boolean(),
            'language_name' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'replication_level' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'target_low' => $this->faker->numberBetween(-10000, 10000),
            'target_high' => $this->faker->numberBetween(-10000, 10000),
            'weighting_factor' => $this->faker->numberBetween(-10000, 10000),
            'description' => $this->faker->text(),
        ];
    }
}
