<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelApplet;
use App\Models\SiebelBusinessComponent;
use App\Models\SiebelClass;
use App\Models\SiebelProject;

class SiebelAppletFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelApplet::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'title' => $this->faker->sentence(4),
            'title_string_reference' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'title_string_override' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'search_specification' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'associate_applet' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'type' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'no_delete' => $this->faker->boolean(),
            'no_insert' => $this->faker->boolean(),
            'no_merge' => $this->faker->boolean(),
            'no_update' => $this->faker->boolean(),
            'html_number_of_rows' => $this->faker->numberBetween(-10000, 10000),
            'scripted' => $this->faker->boolean(),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{600}'),
            'auto_query_mode' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'background_bitmap_style' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'html_popup_dimension' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'height' => $this->faker->numberBetween(-10000, 10000),
            'help_identifier' => $this->faker->regexify('[A-Za-z0-9]{150}'),
            'insert_position' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'mail_address_field' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'mail_template' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'popup_dimension' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'upgrade_ancestor' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'width' => $this->faker->numberBetween(-10000, 10000),
            'upgrade_behavior' => $this->faker->regexify('[A-Za-z0-9]{25}'),
            'icl_upgrade_path' => $this->faker->numberBetween(-10000, 10000),
            'task' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'default_applet_method' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'default_double_click_method' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'disable_dataloss_warning' => $this->faker->boolean(),
            'object_locked' => $this->faker->boolean(),
            'project_id' => SiebelProject::factory(),
            'business_component_id' => SiebelBusinessComponent::factory(),
            'class_id' => SiebelClass::factory(),
        ];
    }
}
