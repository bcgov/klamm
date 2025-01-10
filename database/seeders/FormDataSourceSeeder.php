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
                'endpoint' => '/fwd/v1.0/data/Forms Case/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization", "getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            1 => 
            array(
                'id' => 2,
                'name' => 'Contact',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Contact/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            2 => 
            array(
                'id' => 3,
                'name' => 'Service Request',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Service Request/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            3 => 
            array(
                'id' => 4,
                'name' => 'Benefit Plan',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Benefit Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            4 => 
            array(
                'id' => 5,
                'name' => 'Case Review',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Case Review/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            5 => 
            array(
                'id' => 6,
                'name' => 'Incident',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Incident/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            6 => 
            array(
                'id' => 7,
                'name' => 'Service Order',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Service Order/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            7 => 
            array(
                'id' => 8,
                'name' => 'Service Plan',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Service Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            8 => 
            array(
                'id' => 9,
                'name' => 'Service Provider',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Service Provider/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
            9 => 
            array(
                'id' => 10,
                'name' => 'Transacation Summary',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Transaction Summary/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'https://sieblab-data.api.gov.bc.ca',
            ),
        ));
    }
}
