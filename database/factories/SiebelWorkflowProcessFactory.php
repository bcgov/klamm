<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessObject;
use App\Models\SiebelProject;
use App\Models\SiebelWorkflowProcess;

class SiebelWorkflowProcessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelWorkflowProcess::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'auto_persist' => $this->faker->boolean(),
            'process_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'simulate_workflow_process' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'status' => $this->faker->regexify('[A-Za-z0-9]{40}'),
            'workflow_mode' => $this->faker->regexify('[A-Za-z0-9]{40}'),
            'changed' => $this->faker->boolean(),
            'group' => $this->faker->regexify('[A-Za-z0-9]{40}'),
            'version' => $this->faker->numberBetween(-10000, 10000),
            'description' => $this->faker->text(),
            'error_process_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'state_management_type' => $this->faker->regexify('[A-Za-z0-9]{40}'),
            'web_service_enabled' => $this->faker->boolean(),
            'pass_by_ref_hierarchy_argument' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'business_object_id' => SiebelBusinessObject::factory(),
        ];
    }
}
