<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\SiebelApplication;
use App\Models\SiebelProject;
use App\Models\SiebelScreen;

class SiebelApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiebelApplication::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'changed' => $this->faker->boolean(),
            'repository_name' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'menu' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'scripted' => $this->faker->boolean(),
            'acknowledgment_web_page' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'container_web_page' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'error_web_page' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            'login_web_page' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'logoff_acknowledgment_web_page' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'acknowledgment_web_view' => $this->faker->regexify('[A-Za-z0-9]{250}'),
            'default_find' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'inactive' => $this->faker->boolean(),
            'comments' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'object_locked' => $this->faker->boolean(),
            'object_language_locked' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'project_id' => SiebelProject::factory(),
            'task_screen_id' => SiebelScreen::factory(),
        ];
    }
}
