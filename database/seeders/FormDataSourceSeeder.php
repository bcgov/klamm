<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormDataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * @return void
     */
    public function run()
    {
        \DB::table('form_data_sources')->delete();

        \DB::table('form_data_sources')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Case',
                'source' => '/api/getCaseData',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Contact',
                'source' => '/api/getContactData',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'SR',
                'source' => '/api/getSRData',
            ),
        ));
    }
}
