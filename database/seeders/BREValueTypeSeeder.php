<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\BREValueType;

class BREValueTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $number = new BREValueType();
        $number->name = 'Unsigned Integer';
        $number->description = 'An unsigned integer is a whole number greater than or equal to zero.';
        $number->save();

        $number = new BREValueType();
        $number->name = 'Integer';
        $number->description = 'An integer is a whole number, but it may be less than zero (ie. negative values)';
        $number->save();

        $number = new BREValueType();
        $number->name = 'Float';
        $number->description = 'A Float, or Floating Point number, is a numeric value with with possible fractional values';
        $number->save();

        $identifier = new BREValueType();
        $identifier->name = 'Identifier';
        $identifier->description = 'An identifier is an alphanumeric value with no internal semantics - it serves only to index a data collection';
        $identifier->save();

        $enumNumeric = new BREValueType();
        $enumNumeric->name = 'Enum - Numeric';
        $enumNumeric->description = 'An enumerated numeric value is a non-contiguous set of possible values (e.g. 3, 6, 9)';
        $enumNumeric->save();

        $enumString = new BREValueType();
        $enumString->name = 'Enum - String';
        $enumString->description = 'An enumerated string value is one of a set of pre-defined possible values (e.g. Minor, Adult, Senior)';
        $enumString->save();

        $string = new BREValueType();
        $string->name = 'String';
        $string->description = 'String values are for short text inputs - (e.g. First Name, City)';
        $string->save();

        $longtext = new BREValueType();
        $longtext->name = 'Text';
        $longtext->description = 'Text values are longer-form text inputs - (e.g. descriptions, requests, etc.)';
        $longtext->save();

        $boolean = new BREValueType();
        $boolean->name = 'Boolean';
        $boolean->description = 'Boolean values are for true/false inputs - (e.g. Active, Inactive)';
        $boolean->save();

        $blob = new BREValueType();
        $blob->name = 'Blob';
        $blob->description = 'Blob values are file ipunts or data- (e.g. attachments, formdata, etc.)';
        $blob->save();
    }
}
