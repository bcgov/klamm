<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessComponent;
use App\Models\SiebelLink;
use App\Models\SiebelProject;
use App\Models\SiebelTable;

class SiebelLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelLink::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'source_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'destination_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inter_parent_column' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inter_child_column' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inter_child_delete' => $this->faker->boolean(),
            'primary_id_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'cascade_delete' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'search_specification' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'association_list_sort_specification' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'no_associate' => $this->faker->boolean(),
            'no_delete' => $this->faker->boolean(),
            'no_insert' => $this->faker->boolean(),
            'no_inter_delete' => $this->faker->boolean(),
            'no_update' => $this->faker->boolean(),
            'visibility_auto_all' => $this->faker->boolean(),
            'visibility_rule_applied' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'visibility_type' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'parent_business_component_id' => SiebelBusinessComponent::factory(),
            'child_business_component_id' => SiebelBusinessComponent::factory(),
            'inter_table_id' => SiebelTable::factory(),
        ];
    }
}
