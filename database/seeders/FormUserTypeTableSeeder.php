<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormUserTypeTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('form_user_type')->truncate();

        DB::table('form_user_type')->insert([
            ['form_id' => 7, 'user_type_id' => 1],
            ['form_id' => 16, 'user_type_id' => 1],
            ['form_id' => 26, 'user_type_id' => 1],
            ['form_id' => 39, 'user_type_id' => 1],
            ['form_id' => 59, 'user_type_id' => 2],
            ['form_id' => 61, 'user_type_id' => 1],
            ['form_id' => 155, 'user_type_id' => 2],
            ['form_id' => 157, 'user_type_id' => 2],
            ['form_id' => 158, 'user_type_id' => 2],
            ['form_id' => 159, 'user_type_id' => 2],
            ['form_id' => 160, 'user_type_id' => 2],
            ['form_id' => 163, 'user_type_id' => 2],
            ['form_id' => 206, 'user_type_id' => 2],
            ['form_id' => 215, 'user_type_id' => 2],
            ['form_id' => 221, 'user_type_id' => 2],
            ['form_id' => 231, 'user_type_id' => 2],
            ['form_id' => 327, 'user_type_id' => 2],
            ['form_id' => 381, 'user_type_id' => 2],
            ['form_id' => 403, 'user_type_id' => 1],
            ['form_id' => 422, 'user_type_id' => 1],
            ['form_id' => 572, 'user_type_id' => 1],
            ['form_id' => 577, 'user_type_id' => 1],
            ['form_id' => 636, 'user_type_id' => 1],
            ['form_id' => 678, 'user_type_id' => 2],
            ['form_id' => 679, 'user_type_id' => 2],
            ['form_id' => 734, 'user_type_id' => 1],
            ['form_id' => 757, 'user_type_id' => 2],
            ['form_id' => 778, 'user_type_id' => 1],
            ['form_id' => 876, 'user_type_id' => 1],
            ['form_id' => 891, 'user_type_id' => 1],
            ['form_id' => 901, 'user_type_id' => 1],
            ['form_id' => 902, 'user_type_id' => 1],
            ['form_id' => 908, 'user_type_id' => 1],
            ['form_id' => 909, 'user_type_id' => 2],
            ['form_id' => 960, 'user_type_id' => 1],
            ['form_id' => 967, 'user_type_id' => 2],
            ['form_id' => 982, 'user_type_id' => 2],
            ['form_id' => 988, 'user_type_id' => 1],
            ['form_id' => 989, 'user_type_id' => 1],
            ['form_id' => 990, 'user_type_id' => 1],
            ['form_id' => 1004, 'user_type_id' => 1],
            ['form_id' => 1005, 'user_type_id' => 1],
            ['form_id' => 1008, 'user_type_id' => 1],
            ['form_id' => 1009, 'user_type_id' => 1],
            ['form_id' => 1010, 'user_type_id' => 1],
            ['form_id' => 1011, 'user_type_id' => 1],
            ['form_id' => 1132, 'user_type_id' => 1],
            ['form_id' => 1163, 'user_type_id' => 2],
            ['form_id' => 1445, 'user_type_id' => 2],
            ['form_id' => 1459, 'user_type_id' => 1],
            ['form_id' => 1583, 'user_type_id' => 2],
            ['form_id' => 1605, 'user_type_id' => 2],
            ['form_id' => 1613, 'user_type_id' => 1],
            ['form_id' => 1631, 'user_type_id' => 1],
            ['form_id' => 1842, 'user_type_id' => 2],
            ['form_id' => 1873, 'user_type_id' => 2],
            ['form_id' => 1905, 'user_type_id' => 1],
            ['form_id' => 1906, 'user_type_id' => 1],
            ['form_id' => 2004, 'user_type_id' => 1],
        ]);
    }
}
