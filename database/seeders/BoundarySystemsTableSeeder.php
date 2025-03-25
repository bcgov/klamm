<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BoundarySystemsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('boundary_systems')->delete();

        \DB::table('boundary_systems')->insert(array(
            array(
                'id' => 1,
                'interface_name' => 'CPP Service Canada Request - INT683',
                'interface_description' => 'To transfer SIN and Date of Birth information related to SDPR clients in ICM to Service Canada. Service Canada will use this information to send over CPP Payments received by matching clients back to ICM.',
                'boundary_system_source_system_id' => 1,
                'boundary_system_target_system_id' => 4,
                'boundary_system_mode_of_transfer_id' => 1,
                'boundary_system_file_format_id' => 1,
                'boundary_system_frequency_id' => 3,
                'date_time' => 'Friday after the EA Payment Run and should be sent post business hours',
                'source_point_of_contact' => 'SDSI.ICMApplicationSupport@gov.bc.ca
ICM.WebMethods.PROD@gov.bc.ca',
                'target_point_of_contact' => 'NC-ORD-BDRO-GD@servicecanada.gc.ca',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            array(
                'id' => 2,
                'interface_name' => 'CPP Service Canada Response - INT684',
                'interface_description' => 'To receive CPP Payment details from Service Canada based on the SIN/Date of Birth related file sent previously as part of the CPP Service Canada Request interface',
                'boundary_system_source_system_id' => 4,
                'boundary_system_target_system_id' => 1,
                'boundary_system_mode_of_transfer_id' => 1,
                'boundary_system_file_format_id' => 1,
                'boundary_system_frequency_id' => 3,
                'date_time' => 'First business day following the request file being sent to SC',
                'source_point_of_contact' => 'FTPEXCHANGE@hrsdc-rhdcc.gc.ca',
                'target_point_of_contact' => 'SDSI.ICMApplicationSupport@gov.bc.ca

ICM.WebMethods.PROD@gov.bc.ca',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            array(
                'id' => 3,
                'interface_name' => 'BCEA Payments to BC Mail Plus
(Master Print File)',
                'interface_description' => 'Payment details are sent to BC Mail Plus for printing, stuffing, and mailing.ï¿½Includes the office cheques followed by the sorted mail documents and formats them into a data print stream, inserting cheque and document numbers, insert information for the bottom of cheque, and other information',
                'boundary_system_source_system_id' => 2,
                'boundary_system_target_system_id' => 5,
                'boundary_system_mode_of_transfer_id' => 2,
                'boundary_system_file_format_id' => 1,
                'boundary_system_frequency_id' => 3,
                'date_time' => 'Friday cutoff at 8:30 pm',
                'source_point_of_contact' => 'OPERHELP@gov.bc.ca',
                'target_point_of_contact' => 'Most contact is directly between BCMail and the MIS Prod Control team.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            array(
                'id' => 4,
                'interface_name' => 'EFT Payments to Provincial Treasury',
                'interface_description' => 'EFT payments for BCEA clients and suppliers are sent to Provincial Treasury for distribution to bank accounts.  No Response file.',
                'boundary_system_source_system_id' => 2,
                'boundary_system_target_system_id' => 6,
                'boundary_system_mode_of_transfer_id' => 2,
                'boundary_system_file_format_id' => 1,
                'boundary_system_frequency_id' => 3,
                'date_time' => 'Friday cutoff at 8:30 pm',
                'source_point_of_contact' => 'OPERHELP@gov.bc.ca',
                'target_point_of_contact' => 'BCMCMGMT@Victoria1.gov.bc.ca 
BankingRelations@gov.bc.ca 
FINTRSPS@gov.bc.ca',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
    }
}
