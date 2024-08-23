<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BREDataType;
use App\Models\BREValueType;

class BREDataTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stringValueType = BREValueType::where('name', 'String')->first()->id;
        $integerValueType = BREValueType::where('name', 'Integer')->first()->id;
        $booleanValueType = BREValueType::where('name', 'Boolean')->first()->id;
        $longTextValueType = BREValueType::where('name', 'Text')->first()->id;
        $blobValueType = BREValueType::where('name', 'Blob')->first()->id;

        $dataTypes = [
            ['name' => 'text-input', 'short_description' => 'Text Input', 'value_type_id' => $stringValueType],
            ['name' => 'dropdown', 'short_description' => 'Dropdown', 'value_type_id' => $stringValueType],
            ['name' => 'checkbox', 'short_description' => 'Checkbox', 'value_type_id' => $booleanValueType],
            ['name' => 'toggle', 'short_description' => 'Toggle', 'value_type_id' => $stringValueType],
            ['name' => 'true-false', 'short_description' => 'True/False', 'value_type_id' => $booleanValueType],
            ['name' => 'date', 'short_description' => 'Date', 'value_type_id' => $stringValueType],
            ['name' => 'text-area', 'short_description' => 'Text Area', 'value_type_id' => $stringValueType],
            ['name' => 'button', 'short_description' => 'Button', 'value_type_id' => $stringValueType],
            ['name' => 'radio', 'short_description' => 'Radio Button', 'value_type_id' => $stringValueType],
            ['name' => 'number-input', 'short_description' => 'Number Input', 'value_type_id' => $integerValueType],
            ['name' => 'text-info', 'short_description' => 'Text Info', 'value_type_id' => $longTextValueType],
            ['name' => 'link', 'short_description' => 'Link', 'value_type_id' => $stringValueType],

        ];

        foreach ($dataTypes as $dataType) {
            BREDataType::create($dataType);
        }
    }
}
