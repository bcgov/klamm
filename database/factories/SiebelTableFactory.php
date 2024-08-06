<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelProject;
use App\Models\SiebelTable;

class SiebelTableFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelTable::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'object_locked' => $this->faker->boolean(),
            'object_locked_by_name' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'object_locked_date' => $this->faker->dateTime(),
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'user_name' => $this->faker->userName(),
            'alias' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'type' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'file' => $this->faker->boolean(),
            'abbreviation_1' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'abbreviation_2' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'abbreviation_3' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'append_data' => $this->faker->boolean(),
            'dflt_mapping_col_name_prefix' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'seed_filter' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'seed_locale_filter' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'seed_usage' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'group' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'owner_organization_specifier' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'status' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'volatile' => $this->faker->boolean(),
            'inactive' => $this->faker->boolean(),
            'node_type' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'partition_indicator' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'external_api_write' => $this->faker->boolean(),
            'project_id' => SiebelProject::factory(),
            'base_table_id' => SiebelTable::factory(),
        ];
    }
}
