<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\DataSource;
use App\Models\DataSourceFields;

class DataSourceFieldsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DataSourceFields::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'data_source_id' => DataSource::factory(),
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
        ];
    }
}
