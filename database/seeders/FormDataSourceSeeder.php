<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class FormDataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * @return void
     */
    public function run()
    {
        DB::table('form_data_sources')->truncate();

        DB::table('form_data_sources')->insert([
            [
                'name' => 'Case',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Case/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization", "getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Contact',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Contact/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Service Request',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Request/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Benefit Plan',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Benefit Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Case Review',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Case Review/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Incident',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Incident/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Service Order',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Order/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Service Plan',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Plan/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Service Provider',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Service Provider/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
            [
                'name' => 'Transacation Summary',
                'type' => 'GET',
                'endpoint' => 'ICM REST Forms Transaction Summary/DT Form Instance/@@attachmentId',
                'params' => '{"ViewMode":"Organization","getChildren":"all"}',
                'body' => '',
                'headers' => '{"Authorization":"Bearer @@token@@"}',
                'host' => 'SIEBEL_ICM_API_HOST',
            ],
        ]);
    }
}
