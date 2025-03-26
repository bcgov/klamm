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
        $objectArrayValueType = BREValueType::where('name', 'Object Array')->first()->id;

        $dataTypes = [
            ['name' => 'text-input', 'short_description' => 'Text Input', 'value_type_id' => $stringValueType],
            ['name' => 'true-false', 'short_description' => 'True/False', 'value_type_id' => $booleanValueType],
            ['name' => 'date', 'short_description' => 'Date', 'value_type_id' => $stringValueType],
            ['name' => 'number-input', 'short_description' => 'Number Input', 'value_type_id' => $integerValueType],
            ['name' => 'object-array', 'short_description' => 'Array of Objects', 'value_type_id' => $objectArrayValueType],
        ];

        foreach ($dataTypes as $dataType) {
            BREDataType::create($dataType);
        }
    }
}
