<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Ministry;

class MinistryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ministry::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'short_name' => $this->faker->regexify('[A-Za-z0-9]{20}'),
            'name' => $this->faker->name(),
        ];
    }
}
