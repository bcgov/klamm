<?php

namespace Database\Factories;

use App\Models\BREField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ICMCDWField>
 */
class ICMCDWFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'field' => $this->faker->name(),
            'panel_type' => $this->faker->sentence(),
            'entity' => $this->faker->sentence(),
            'path' => $this->faker->sentence(),
            'subject_area' => $this->faker->sentence(),
            'applet' => $this->faker->sentence(),
            'datatype' => $this->faker->sentence(),
            'field_input_max_length' => $this->faker->numberBetween(-10000, 10000),
            'ministry' => $this->faker->sentence(),
            'cdw_ui_caption' => $this->faker->sentence(),
            'cdw_table_name' => $this->faker->name(),
            'cdw_column_name' => $this->faker->name(),
            'bre_fields' => BREField::factory()->count(3)->make()->toArray(),
            //
        ];
    }
}
