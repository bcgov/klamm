<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessComponent;
use App\Models\SiebelClass;
use App\Models\SiebelProject;
use App\Models\SiebelTable;

class SiebelBusinessComponentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelBusinessComponent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'cache_data' => $this->faker->boolean(),
            'data_source' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'dirty_reads' => $this->faker->boolean(),
            'distinct' => $this->faker->boolean(),
            'enclosure_id_field' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'force_active' => $this->faker->boolean(),
            'gen_reassign_act' => $this->faker->boolean(),
            'hierarchy_parent_field' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'type' => $this->faker->randomElement(["Transient","Non-Transient"]),
            'inactive' => $this->faker->boolean(),
            'insert_update_all_columns' => $this->faker->boolean(),
            'log_changes' => $this->faker->boolean(),
            'maximum_cursor_size' => $this->faker->numberBetween(-10000, 10000),
            'multirecipient_select' => $this->faker->boolean(),
            'no_delete' => $this->faker->boolean(),
            'no_insert' => $this->faker->boolean(),
            'no_update' => $this->faker->boolean(),
            'no_merge' => $this->faker->boolean(),
            'owner_delete' => $this->faker->boolean(),
            'placeholder' => $this->faker->boolean(),
            'popup_visibility_auto_all' => $this->faker->boolean(),
            'popup_visibility_type' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'prefetch_size' => $this->faker->numberBetween(-10000, 10000),
            'recipient_id_field' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'reverse_fill_threshold' => $this->faker->numberBetween(-10000, 10000),
            'scripted' => $this->faker->boolean(),
            'search_specification' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'sort_specification' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'status_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'synonym_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'upgrade_ancestor' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'xa_attribute_value_bus_comp' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'xa_class_id_field' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'class_id' => SiebelClass::factory(),
            'table_id' => SiebelTable::factory(),
        ];
    }
}
