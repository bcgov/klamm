<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelProject;
use App\Models\SiebelScreen;
use App\Models\SiebelView;

class SiebelScreenFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelScreen::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'bitmap_category' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'viewbar_text' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'viewbar_text_string_reference' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'viewbar_text_string_override' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'unrestricted_viewbar' => $this->faker->boolean(),
            'help_identifier' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'upgrade_behavior' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'default_view_id' => SiebelView::factory(),
        ];
    }
}
