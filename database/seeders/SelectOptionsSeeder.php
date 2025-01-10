<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormField;
use App\Models\SelectOptions;

class SelectOptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $eyeColorFormField = FormField::where('name', 'eye_colour')->first();

        if ($eyeColorFormField) {
            $selectOptions = [
                ['name' => 'eye_colour_brown', 'label' => 'Brown', 'value' => '1'],
                ['name' => 'eye_colour_black', 'label' => 'Black', 'value' => '2'],
                ['name' => 'eye_colour_blue', 'label' => 'Blue', 'value' => '3'],
                ['name' => 'eye_colour_green', 'label' => 'Green', 'value' => '4'],
            ];

            foreach ($selectOptions as $optionData) {
                $selectOption = SelectOptions::create($optionData);

                $eyeColorFormField->selectOptions()->attach($selectOption->id);
            }
        }
    }
}
