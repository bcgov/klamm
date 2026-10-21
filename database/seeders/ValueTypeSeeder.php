<?php

namespace Database\Seeders;

use App\Models\ValueType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class ValueTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Unsigned Integer' => 'An unsigned integer is a whole number greater than or equal to zero.',
            'Integer' => 'An integer is a whole number, but it may be less than zero (ie. negative values)',
            'Float' => 'A Float, or Floating Point number, is a numeric value with possible fractional values',
            'Identifier' => 'An identifier is an alphanumeric value with no internal semantics - it serves only to index a data collection',
            'Enum - Numeric' => 'An enumerated numeric value is a non-contiguous set of possible values (e.g. 3, 6, 9)',
            'Enum - String' => 'An enumerated string value is one of a set of pre-defined possible values (e.g. Minor, Adult, Senior)',
            'String' => 'String values are for short text inputs (e.g. First Name, City)',
            'Text' => 'Text values are longer-form text inputs (e.g. descriptions, requests, etc.)',
            'Blob' => 'Blob values are file inputs or raw data (e.g. attachments, formdata, etc.)',
        ];

        foreach ($types as $name => $description) {
            ValueType::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }
    }
}
