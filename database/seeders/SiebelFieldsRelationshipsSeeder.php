<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SiebelFieldsRelationshipsSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '1024M');

        \DB::table('siebel_field_references')->delete();
        \DB::table('siebel_field_values')->delete();


        $this->call([
            SiebelFieldReferencesSeeder::class,
            SiebelFieldValuesSeeder::class,
        ]);
    }
}
