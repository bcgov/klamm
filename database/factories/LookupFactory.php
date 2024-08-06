<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Lookup;

class LookupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Lookup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'lookup_field' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'lookup_table' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'lookup_table_code' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'lookup_database' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'description' => $this->faker->text(),
        ];
    }
}
