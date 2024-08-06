<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelProject;
use App\Models\SiebelWebTemplate;

class SiebelWebTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelWebTemplate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'definition' => $this->faker->text(),
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'type' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
        ];
    }
}
