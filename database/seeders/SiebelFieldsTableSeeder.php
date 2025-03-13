<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SiebelFieldsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '1024M');

        \DB::table('siebel_fields')->delete();

        $this->call([
            SiebelBusinossComponentsTableSeeder1::class,
            SiebelFieldsTableSeeder1::class,
            SiebelFieldsTableSeeder2::class,
            SiebelFieldsTableSeeder3::class,
            SiebelFieldsTableSeeder4::class,
            SiebelFieldsTableSeeder5::class,
            SiebelFieldsTableSeeder6::class,
            SiebelFieldsTableSeeder7::class,
            SiebelFieldsTableSeeder8::class,
            SiebelFieldsTableSeeder9::class,
            SiebelFieldsTableSeeder10::class,
        ]);
    }
}
