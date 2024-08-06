<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelProject;
use App\Models\SiebelWebPage;

class SiebelWebPageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelWebPage::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'do_not_use_container' => $this->faker->boolean(),
            'title' => $this->faker->sentence(4),
            'title_string_reference' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'web_template' => $this->faker->regexify('[A-Za-z0-9]{200}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{500}'),
            'object_locked' => $this->faker->boolean(),
            'project_id' => SiebelProject::factory(),
        ];
    }
}
