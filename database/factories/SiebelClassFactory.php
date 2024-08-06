<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelClass;
use App\Models\SiebelProject;

class SiebelClassFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelClass::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'dll' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'object_type' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'thin_client' => $this->faker->boolean(),
            'java_thin_client' => $this->faker->boolean(),
            'handheld_client' => $this->faker->boolean(),
            'unix_support' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'high_interactivity_enabled' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'super_class_id' => SiebelClass::factory(),
        ];
    }
}
