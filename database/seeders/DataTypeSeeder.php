<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DataType;
use App\Models\ValueType;

class DataTypeSeeder extends Seeder
{
    public function run()
    {
        $stringValueType = ValueType::where('name', 'String')->first()->id;
        $integerValueType = ValueType::where('name', 'Integer')->first()->id;
        $longTextValueType = ValueType::where('name', 'Text')->first()->id;
        $blobValueType = ValueType::where('name', 'Blob')->first()->id;

        $dataTypes = [
            ['name' => 'text-input', 'short_description' => 'Text Input', 'value_type_id' => $stringValueType],
            ['name' => 'dropdown', 'short_description' => 'Dropdown', 'value_type_id' => $stringValueType],
            ['name' => 'checkbox', 'short_description' => 'Checkbox', 'value_type_id' => $stringValueType],
            ['name' => 'toggle', 'short_description' => 'Toggle', 'value_type_id' => $stringValueType],
            ['name' => 'date', 'short_description' => 'Date', 'value_type_id' => $stringValueType],
            ['name' => 'text-area', 'short_description' => 'Text Area', 'value_type_id' => $stringValueType], 
            ['name' => 'button', 'short_description' => 'Button', 'value_type_id' => $stringValueType],
            ['name' => 'radio', 'short_description' => 'Radio Button', 'value_type_id' => $stringValueType],
            ['name' => 'number-input', 'short_description' => 'Number Input', 'value_type_id' => $integerValueType],
            ['name' => 'text-info', 'short_description' => 'Text Info', 'value_type_id' => $longTextValueType],
            ['name' => 'link', 'short_description' => 'Link', 'value_type_id' => $stringValueType],
            ['name' => 'file', 'short_description' => 'File', 'value_type_id' => $blobValueType],
            ['name' => 'table', 'short_description' => 'Table', 'value_type_id' => $blobValueType],
            
        ];

        foreach ($dataTypes as $dataType) {
            DataType::create($dataType);
        }
    }
}
