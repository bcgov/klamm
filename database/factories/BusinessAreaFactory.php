<?php

namespace Database\Factories;

use App\Models\BusinessArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessArea>
 */
class BusinessAreaFactory extends Factory
{
    protected $model = BusinessArea::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Business Area',
            'description' => $this->faker->paragraph(),
            'short_name' => $this->faker->unique()->lexify('???'),
        ];
    }
}
