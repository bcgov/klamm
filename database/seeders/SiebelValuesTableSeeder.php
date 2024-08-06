<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SiebelValuesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '1024M');
        
        \DB::table('siebel_values')->delete();
        
        $this->call([
            SiebelValuesTableSeeder1::class,
            SiebelValuesTableSeeder2::class,
        ]);
    }
}