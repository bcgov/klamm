<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Jeremy',
                'email' => 'jeremy.vernon@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:01',
                'password' => '$2y$12$0QQN/Xxb1XIvnkCyLHfife1ch2fU11Tw5k40z.ojSQ9ryvS1nEbeG',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:01',
                'updated_at' => '2024-12-18 23:08:01',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Bojan',
                'email' => 'bojan.zimonja@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:01',
                'password' => '$2y$12$ylIh/O1LxLA6q3YPA7Wxzu.hZz.2s6jUxj5my9MjJFdyu29n3/oBq',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:01',
                'updated_at' => '2024-12-18 23:08:01',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Will',
                'email' => 'will.kiiskila@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:01',
                'password' => '$2y$12$l7HUDQ53WX9oZtkIkhSbfe9L7yNkovz/ACY178jZnTFQberqQ3/Ci',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:02',
                'updated_at' => '2024-12-18 23:08:02',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Tim',
                'email' => 'tim.vanderwekken@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:02',
                'password' => '$2y$12$VingXyVkNSU8ee/cdCr67um/snKtdlu6H49lA2zeGcSB23bb5snRS',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:02',
                'updated_at' => '2024-12-18 23:08:02',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Saranya',
                'email' => 'saranya.viswam@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:02',
                'password' => '$2y$12$7lQGWb56UocM7/ALg9bRGO5rqdhlKiF05k7rTNjnSNMUWrpg7gnkC',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:02',
                'updated_at' => '2024-12-18 23:08:02',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Bryson',
                'email' => 'bryson.best@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:02',
                'password' => '$2y$12$3oxpvYJJTxTuSCj/1CA.F.Wz0sxFR7qAslSiY017zmbHdzkKef2/q',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:02',
                'updated_at' => '2024-12-18 23:08:02',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'David',
                'email' => 'david.okulski@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:02',
                'password' => '$2y$12$zweAgtkOmNvtJ2c0MocpGO0Wx7jGgoRT7RjBFfwTmGnqNvMajJNgO',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:03',
                'updated_at' => '2024-12-18 23:08:03',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Joh',
                'email' => 'johtaro.yoshida@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:03',
                'password' => '$2y$12$OUQEt2Y9uzvWRdkpzMypz.O9CgLEAdTc5P2wRE6lOTruWkXfBeab.',
                'remember_token' => NULL,
                'created_at' => '2024-12-18 23:08:03',
                'updated_at' => '2024-12-18 23:08:03',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Josh',
                'email' => 'joshua.larouche@gov.bc.ca',
                'email_verified_at' => '2024-12-18 23:08:03',
                'password' => '$2y$12$SOoK6YjOU0WQl7QpH2mK2euwAUq1s7QFjuHsmQZyukdSKsyHz35li',
                'remember_token' => 'RytjoGJ73UNT5GMZwKw8C2BkrviPpd5oucwxwYl1BdL34Rs352km6QOLOCXN',
                'created_at' => '2024-12-18 23:08:03',
                'updated_at' => '2024-12-18 23:08:03',
            ),
        ));
        
        
    }
}