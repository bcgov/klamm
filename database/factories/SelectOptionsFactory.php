<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\DataType;
use App\Models\SelectOptions;
use App\Models\FormField;

class SelectOptionsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SelectOptions::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'label' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'value' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'description' => $this->faker->text(),
            'form_field_id' => FormField::factory(),
        ];
    }
}
