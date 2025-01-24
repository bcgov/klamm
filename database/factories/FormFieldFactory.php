<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\DataSource;
use App\Models\DataType;
use App\Models\FieldGroup;
use App\Models\FormField;

class FormFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FormField::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'label' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'help_text' => $this->faker->text(),
            'data_type_id' => DataType::factory(),
            'description' => $this->faker->text(),
            'field_group_id' => FieldGroup::factory(),
            'validation' => $this->faker->text(),
            'required' => $this->faker->boolean(),
            'repeater' => $this->faker->boolean(),
            'max_count' => $this->faker->word(),
            'prepopulated' => $this->faker->boolean(),
        ];
    }
}
