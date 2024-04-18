<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\BusinessForm;

class BusinessFormFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BusinessForm::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'code' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'short_description' => $this->faker->text(),
            'long_description' => $this->faker->text(),
            'internal_description' => $this->faker->text(),
            'ado_identifier' => $this->faker->text(),
        ];
    }
}
