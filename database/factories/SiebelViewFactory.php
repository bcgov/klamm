<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelBusinessObject;
use App\Models\SiebelProject;
use App\Models\SiebelView;

class SiebelViewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelView::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'visibility_applet' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'visibility_applet_type' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'admin_mode_flag' => $this->faker->boolean(),
            'thread_applet' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'thread_field' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'thread_title' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'thread_title_string_reference' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'thread_title_string_override' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'bitmap_category' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'drop_sectors' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'explicit_login' => $this->faker->boolean(),
            'help_identifier' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'no_borders' => $this->faker->boolean(),
            'screen_menu' => $this->faker->boolean(),
            'sector0_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector1_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector2_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector3_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector4_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector5_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector6_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'sector7_applet' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'secure' => $this->faker->boolean(),
            'status_text' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'status_text_string_reference' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'status_text_string_override' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'title' => $this->faker->sentence(4),
            'title_string_reference' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'title_string_override' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'vertical_line_position' => $this->faker->numberBetween(-10000, 10000),
            'upgrade_behavior' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'icl_upgrade_path' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'add_to_history' => $this->faker->boolean(),
            'task' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'type' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'default_applet_focus' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'disable_pdq' => $this->faker->boolean(),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{20}'),
            'business_object_id' => SiebelBusinessObject::factory(),
            'project_id' => SiebelProject::factory(),
        ];
    }
}
