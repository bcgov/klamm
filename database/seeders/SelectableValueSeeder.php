<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SelectableValue;

class SelectableValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $selectableValues = [
            ['name' => 'eye_colour_brown', 'label' => 'Brown', 'value' => '1', 'description' => 'Brown eye colour'],
            ['name' => 'eye_colour_black', 'label' => 'Black', 'value' => '2', 'description' => 'Black eye colour'],
            ['name' => 'eye_colour_blue', 'label' => 'Blue', 'value' => '3', 'description' => 'Blue eye colour'],
            ['name' => 'eye_colour_green', 'label' => 'Green', 'value' => '4', 'description' => 'Green eye colour'],
        ];

        foreach ($selectableValues as $selectableValue) {
            SelectableValue::updateOrCreate(
                ['name' => $selectableValue['name']],
                $selectableValue
            );
        }
    }
}
