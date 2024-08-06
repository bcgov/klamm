<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        $eyeColorFormFieldType = FormField::where('name', 'eye_colour')->first()->id;

        $selectOptions = [
            ['name' => 'eye_colour_brown', 'label' => 'Brown', 'value' =>'1','form_field_id' => $eyeColorFormFieldType],
            ['name' => 'eye_colour_black', 'label' => 'Black', 'value' =>'2','form_field_id' => $eyeColorFormFieldType],
            ['name' => 'eye_colour_blue', 'label' => 'Blue', 'value' =>'3','form_field_id' => $eyeColorFormFieldType],
            ['name' => 'eye_colour_green', 'label' => 'Green', 'value' =>'4','form_field_id' => $eyeColorFormFieldType],
            
            
        ];

        foreach ($selectOptions as $selectOption) {
            SelectOptions::create($selectOption);
        }

    }

    
}
