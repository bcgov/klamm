<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BREDataValidation;
use App\Models\BREValidationType;

class BREDataValidationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equalExactlyValueType = BREValidationType::where('name', 'Data equal to')->first()->id;
        $regexValueType = BREValidationType::where('name', 'Text Pattern')->first()->id;
        $greaterThanValueType = BREValidationType::where('name', 'Number greater than')->first()->id;
        $greaterThanOrEqualValueType = BREValidationType::where('name', 'Number greater than or equal to')->first()->id;
        $lessThanValueType = BREValidationType::where('name', 'Number less than')->first()->id;
        $lessThanOrEqualValueType = BREValidationType::where('name', 'Number less than or equal to')->first()->id;
        $withinInclusiveRangeValueType = BREValidationType::where('name', 'Number within range (inclusive)')->first()->id;
        $withinExclusiveRangeValueType = BREValidationType::where('name', 'Number within range (exclusive)')->first()->id;
        $dateInclusiveValueType = BREValidationType::where('name', 'Date within range (inclusive)')->first()->id;
        $dateExclusiveValueType = BREValidationType::where('name', 'Date within range (exclusive)')->first()->id;
        $textOptionsValueType = BREValidationType::where('name', 'Text Options')->first()->id;
        $numberOptionsValueType = BREValidationType::where('name', 'Number Options')->first()->id;
        $dateOptionsValueType = BREValidationType::where('name', 'Date Options')->first()->id;

        $dataValidations = [
            ['name' => 'example-exact-match-string', 'description' => 'Exact Match String Example', 'validation_type_id' => $equalExactlyValueType, 'validation_criteria' => 'test'],
            ['name' => 'example-exact-match-number', 'description' => 'Exact Match Number Example', 'validation_type_id' => $equalExactlyValueType, 'validation_criteria' => '100'],
            ['name' => 'example-exact-match-date', 'description' => 'Exact Match Date Example', 'validation_type_id' => $equalExactlyValueType, 'validation_criteria' => '2023-09-24'],
            ['name' => 'example-email', 'description' => 'Email Example', 'validation_type_id' => $regexValueType, 'validation_criteria' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'],
            ['name' => 'example-url', 'description' => 'URL Example', 'validation_type_id' => $regexValueType, 'validation_criteria' => '^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$'],
            ['name' => 'example-phone', 'description' => 'Phone Example', 'validation_type_id' => $regexValueType, 'validation_criteria' => '^(\+1|1)?[\s-]?\(?[2-9]\d{2}\)?[\s-]?\d{3}[\s-]?\d{4}$'],
            ['name' => 'example-sin-number', 'description' => 'SIN Number Example', 'validation_type_id' => $regexValueType, 'validation_criteria' => '^(\d{3}-\d{3}-\d{3}|\d{9})$'],
            ['name' => 'example-number-greater-than', 'description' => 'Number Greater Than Example', 'validation_type_id' => $greaterThanValueType, 'validation_criteria' => '[=num]'],
            ['name' => 'example-number-greater-than-or-equal', 'description' => 'Number Greater Than or Equal Example', 'validation_type_id' => $greaterThanOrEqualValueType, 'validation_criteria' => '60'],
            ['name' => 'example-number-less-than', 'description' => 'Number Less Than Example', 'validation_type_id' => $lessThanValueType, 'validation_criteria' => '45'],
            ['name' => 'example-number-less-than-or-equal', 'description' => 'Number Less Than or Equal Example', 'validation_type_id' => $lessThanOrEqualValueType, 'validation_criteria' => '20'],
            ['name' => 'example-number-within-range', 'description' => 'Number Within Exclusive Range Example', 'validation_type_id' => $withinExclusiveRangeValueType, 'validation_criteria' => '[10, 100]'],
            ['name' => 'example-number-within-inclusive-range', 'description' => 'Number Within Inclusive Range Example', 'validation_type_id' => $withinInclusiveRangeValueType, 'validation_criteria' => '[10, 100]'],
            ['name' => 'example-date-within-inclusive-range', 'description' => 'Date Within Inclusive Range Example', 'validation_type_id' => $dateInclusiveValueType, 'validation_criteria' => '[2023-01-01, 2024-12-25]'],
            ['name' => 'example-date-within-exclusive-range', 'description' => 'Date Within Exclusive Range Example', 'validation_type_id' => $dateExclusiveValueType, 'validation_criteria' => '[2023-01-01, 2024-12-25]'],
            ['name' => 'example-string-options', 'description' => 'String Options Example', 'validation_type_id' => $textOptionsValueType, 'validation_criteria' => '[test, test2, test3]'],
            ['name' => 'example-number-options', 'description' => 'Number Options Example', 'validation_type_id' => $numberOptionsValueType, 'validation_criteria' => '[1, 5, 25, 100]'],
            ['name' => 'example-date-options', 'description' => 'Date Options Example', 'validation_type_id' => $dateOptionsValueType, 'validation_criteria' => '[2023-05-24, 2024-12-25]'],
        ];


        foreach ($dataValidations as $dataValidation) {
            BREDataValidation::create($dataValidation);
        }
    }
}
