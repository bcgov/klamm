<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelProject;

class SiebelProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelProject::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'parent_repository' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'inactive' => $this->faker->boolean(),
            'locked' => $this->faker->boolean(),
            'locked_by_name' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'locked_date' => $this->faker->dateTime(),
            'language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'ui_freeze' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'allow_object_locking' => $this->faker->boolean(),
        ];
    }
}
