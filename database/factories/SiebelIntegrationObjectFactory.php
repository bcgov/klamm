<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessObject;
use App\Models\SiebelIntegrationObject;
use App\Models\SiebelProject;

class SiebelIntegrationObjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelIntegrationObject::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'adapter_info' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'base_object_type' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'external_major_version' => $this->faker->numberBetween(-10000, 10000),
            'external_minor_version' => $this->faker->numberBetween(-10000, 10000),
            'external_name' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'xml_tag' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'business_object_id' => SiebelBusinessObject::factory(),
        ];
    }
}
