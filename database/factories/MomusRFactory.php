<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Integration;
use App\Models\Lookup;
use App\Models\MomusR;
use App\Models\Xml;

class MomusRFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MomusR::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'field_name' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'description' => $this->faker->text(),
            'field_type' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'field_type_length' => $this->faker->numberBetween(-10000, 10000),
            'source' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'screen' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'table' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'condition' => $this->faker->regexify('[A-Za-z0-9]{100}'),
            'table_code' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'lookup_field' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'database_name' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            'integration_id' => Integration::factory(),
            'xml_id' => Xml::factory(),
            'lookup_id' => Lookup::factory(),
            'have_duplicate' => $this->faker->boolean(),
        ];
    }
}
