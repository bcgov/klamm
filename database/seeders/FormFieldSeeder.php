<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormField;
use App\Models\DataType;

class FormFieldSeeder extends Seeder
{
    public function run()
    {
        $textInputType = DataType::where('name', 'text-input')->first()->id;
        $dropdownType = DataType::where('name', 'dropdown')->first()->id;
        $checkboxType = DataType::where('name', 'checkbox')->first()->id;
        $toggleType = DataType::where('name', 'toggle')->first()->id;
        $dateType = DataType::where('name', 'date')->first()->id;
        $textAreaType = DataType::where('name', 'text-area')->first()->id;
        $buttonType = DataType::where('name', 'button')->first()->id;
        $radioType = DataType::where('name', 'radio')->first()->id;
        $numberInputType = DataType::where('name', 'number-input')->first()->id;
        $textInfoType = DataType::where('name', 'text-info')->first()->id;
        $linkType = DataType::where('name', 'link')->first()->id;        
        $fileType = DataType::where('name', 'file')->first()->id;
        $tableType = DataType::where('name', 'table')->first()->id;

        $formFields = [
            ['name' => 'first_name', 'label' => 'First Name', 'data_type_id' => $textInputType],
            ['name' => 'last_name', 'label' => 'Last Name', 'data_type_id' => $textInputType],
            ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'data_type_id' => $dateType],
            ['name' => 'eye_colour', 'label' => 'Eye Colour', 'data_type_id' => $dropdownType],
            ['name' => 'canadian_citizen', 'label' => 'Canadian Citizen', 'data_type_id' => $toggleType],
            ['name' => 'previous_military_service', 'label' => 'Previous Military Service', 'data_type_id' => $checkboxType],
            ['name' => 'comments', 'label' => 'Comments', 'data_type_id' => $textAreaType],
            ['name' => 'submit', 'label' => 'Submit', 'data_type_id' => $buttonType],
            ['name' => 'save', 'label' => 'Save', 'data_type_id' => $buttonType],
            ['name' => 'cancel', 'label' => 'Cancel', 'data_type_id' => $buttonType],
            ['name' => 'age', 'label' => 'Age', 'data_type_id' => $numberInputType],
            ['name' => 'description', 'label' => 'Description', 'data_type_id' => $textInfoType],
            ['name' => 'link', 'label' => 'Link', 'data_type_id' => $linkType],
            ['name' => 'attachment', 'label' => 'Attachment', 'data_type_id' => $fileType],
            ['name' => 'table', 'label' => 'Table', 'data_type_id' => $tableType],
        ];

        foreach ($formFields as $formField) {
            FormField::create($formField);
        }
    }
}
