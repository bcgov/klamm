<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BREValidationType;

class BREValidationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $validationTypes = [
            ['name' => 'Data equal to', 'description' => 'Data must exactly match the value given.', 'value' => '=='],
            ['name' => 'Data not equal to', 'description' => 'Data must not match the value given.', 'value' => '!='],
            ['name' => 'Text Pattern', 'description' => 'Text must match regex pattern.', 'value' => 'regex'],
            ['name' => 'Number greater than or equal to', 'description' => 'Number must be greater than or equal to the value given.', 'value' => '>='],
            ['name' => 'Number greater than', 'description' => 'Number must be greater than the value given.', 'value' => '>'],
            ['name' => 'Number less than or equal to', 'description' => 'Number must be less than or equal to the value given.', 'value' => '<='],
            ['name' => 'Number less than', 'description' => 'Number must be less than the value given.', 'value' => '<'],
            ['name' => 'Date greater than or equal to', 'description' => 'Date must be greater than or equal to the value given.', 'value' => '>='],
            ['name' => 'Date greater than', 'description' => 'Date must be greater than the value given.', 'value' => '>'],
            ['name' => 'Date less than or equal to', 'description' => 'Date must be less than or equal to the value given.', 'value' => '<='],
            ['name' => 'Date less than', 'description' => 'Date must be less than the value given.', 'value' => '<'],
            ['name' => 'Number within range (exclusive)', 'description' => 'Data must be within the range given (exclusive of the initial and end values).', 'value' => '(num)'],
            ['name' => 'Number within range (inclusive)', 'description' => 'Data must be within the range given (inclusive of the initial and end values).', 'value' => '[num]'],
            ['name' => 'Date within range (exclusive)', 'description' => 'Date must be within the range given (exclusive of the initial and end values).', 'value' => '(date)'],
            ['name' => 'Date within range (inclusive)', 'description' => 'Date must be within the range given (inclusive of the initial and end values).', 'value' => '[date]'],
            ['name' => 'Text Options', 'description' => 'Text must exactly match one of the options given.', 'value' => '[=text]'],
            ['name' => 'Number Options', 'description' => 'Number must exactly match one of the options given.', 'value' => '[=num]'],
            ['name' => 'Date Options', 'description' => 'Date must exactly match one of the options given.', 'value' => '[=date]'],
            ['name' => 'Text Multiple Options', 'description' => 'Text must exactly match one or more of the options given.', 'value' => '[=texts]'],
            ['name' => 'Number Multiple Options', 'description' => 'Number must exactly match one or more of the options given.', 'value' => '[=nums]'],
            ['name' => 'Date Multiple Options', 'description' => 'Date must exactly match one or more of the options given.', 'value' => '[=dates]'],
        ];

        foreach ($validationTypes as $validationType) {
            BREValidationType::create($validationType);
        }
    }
}
