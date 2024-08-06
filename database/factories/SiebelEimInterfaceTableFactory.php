<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelEimInterfaceTable;
use App\Models\SiebelProject;
use App\Models\SiebelTable;

class SiebelEimInterfaceTableFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelEimInterfaceTable::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'user_name' => $this->faker->userName(),
            'type' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'file' => $this->faker->boolean(),
            'eim_delete_proc_column' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'eim_export_proc_column' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'eim_merge_proc_column' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'target_table_id' => SiebelTable::factory(),
        ];
    }
}
