<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessService;
use App\Models\SiebelClass;
use App\Models\SiebelProject;

class SiebelBusinessServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelBusinessService::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'cache' => $this->faker->boolean(),
            'display_name' => $this->faker->regexify('[A-Za-z0-9]{150}'),
            'display_name_string_reference' => $this->faker->regexify('[A-Za-z0-9]{150}'),
            'display_name_string_override' => $this->faker->regexify('[A-Za-z0-9]{150}'),
            'external_use' => $this->faker->boolean(),
            'hidden' => $this->faker->boolean(),
            'server_enabled' => $this->faker->boolean(),
            'state_management_type' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'web_service_enabled' => $this->faker->boolean(),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'class_id' => SiebelClass::factory(),
        ];
    }
}
