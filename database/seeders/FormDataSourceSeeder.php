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
            array(
                'id' => 1,
                'name' => 'Case',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Case/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization", "getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            1 => 
            array(
                'id' => 2,
                'name' => 'Contact',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Contact/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            2 => 
            array(
                'id' => 3,
                'name' => 'Service Request',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Request/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            3 => 
            array(
                'id' => 4,
                'name' => 'Benefit Plan',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Benefit Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            4 => 
            array(
                'id' => 5,
                'name' => 'Case Review',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Case Review/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            5 => 
            array(
                'id' => 6,
                'name' => 'Incident',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Incident/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            6 => 
            array(
                'id' => 7,
                'name' => 'Service Order',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Order/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            7 => 
            array(
                'id' => 8,
                'name' => 'Service Plan',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            8 => 
            array(
                'id' => 9,
                'name' => 'Service Provider',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Provider/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
            9 => 
            array(
                'id' => 10,
                'name' => 'Transacation Summary',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Transaction Summary/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ),
        ));
    }
}
