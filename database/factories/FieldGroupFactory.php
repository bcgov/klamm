<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\FieldGroup;

class FieldGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FieldGroup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'label' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'description' => $this->faker->text(),
            'internal_description' => $this->faker->text(),
            'repeater' => $this->faker->boolean(),
        ];
    }
}
