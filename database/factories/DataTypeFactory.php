<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\DataType;
use App\Models\ValueType;

class DataTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DataType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'value_type_id' => ValueType::factory(),
            'short_description' => $this->faker->text(),
            'long_description' => $this->faker->text(),
            'validation' => $this->faker->text(),
        ];
    }
}
