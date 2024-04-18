<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\User;

class ActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'summary' => $this->faker->text(),
            'description' => $this->faker->text(),
            'submitter' => User::factory()->create()->submitter,
            'ado_item' => $this->faker->text(),
        ];
    }
}
