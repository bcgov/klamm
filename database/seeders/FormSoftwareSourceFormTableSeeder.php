<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormSoftwareSourceFormTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate resets the auto-increment / sequence
        DB::table('form_software_source_form')->truncate();

        DB::table('form_software_source_form')->insert([
            ['form_id' => 45,  'form_software_source_id' => 3],
            ['form_id' => 61,  'form_software_source_id' => 3],
            ['form_id' => 387, 'form_software_source_id' => 3],
            ['form_id' => 637, 'form_software_source_id' => 3],
            ['form_id' => 664, 'form_software_source_id' => 3],
            ['form_id' => 759, 'form_software_source_id' => 3],
            ['form_id' => 855, 'form_software_source_id' => 3],
            ['form_id' => 900, 'form_software_source_id' => 3],
            ['form_id' => 901, 'form_software_source_id' => 3],
            ['form_id' => 902, 'form_software_source_id' => 3],
            ['form_id' => 921, 'form_software_source_id' => 3],
            ['form_id' => 922, 'form_software_source_id' => 3],
            ['form_id' => 923, 'form_software_source_id' => 3],
            ['form_id' => 924, 'form_software_source_id' => 3],
            ['form_id' => 925, 'form_software_source_id' => 3],
            ['form_id' => 926, 'form_software_source_id' => 3],
            ['form_id' => 960, 'form_software_source_id' => 3],
            ['form_id' => 961, 'form_software_source_id' => 3],
            ['form_id' => 962, 'form_software_source_id' => 3],
            ['form_id' => 963, 'form_software_source_id' => 3],
            ['form_id' => 964, 'form_software_source_id' => 3],
            ['form_id' => 965, 'form_software_source_id' => 3],
            ['form_id' => 966, 'form_software_source_id' => 3],
            ['form_id' => 967, 'form_software_source_id' => 3],
            ['form_id' => 968, 'form_software_source_id' => 3],
            ['form_id' => 969, 'form_software_source_id' => 3],
            ['form_id' => 970, 'form_software_source_id' => 3],
            ['form_id' => 971, 'form_software_source_id' => 3],
            ['form_id' => 972, 'form_software_source_id' => 3],
            ['form_id' => 973, 'form_software_source_id' => 3],
            ['form_id' => 974, 'form_software_source_id' => 3],
            ['form_id' => 975, 'form_software_source_id' => 3],
            ['form_id' => 976, 'form_software_source_id' => 3],
            ['form_id' => 977, 'form_software_source_id' => 3],
            ['form_id' => 986, 'form_software_source_id' => 3],
            ['form_id' => 991, 'form_software_source_id' => 3],
            ['form_id' => 2014, 'form_software_source_id' => 3],
            ['form_id' => 2025, 'form_software_source_id' => 3],
            ['form_id' => 2026, 'form_software_source_id' => 3],
            ['form_id' => 2027, 'form_software_source_id' => 3],
            ['form_id' => 2028, 'form_software_source_id' => 3],
            ['form_id' => 2029, 'form_software_source_id' => 3],
            ['form_id' => 2030, 'form_software_source_id' => 3],
            ['form_id' => 2052, 'form_software_source_id' => 3],
        ]);
    }
}
