<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BoundarySystemInterface;
use App\Models\BoundarySystem;
use App\Models\BoundarySystemTag;

class BoundarySystemInterfaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $interfaces = [
            [
                'name' => 'BCEA Journal Vouchers to CAS',
                'short_description' => 'Journal Vouchers are sent to CAS/CGI from MIS to post BCEA payments to the General Ledger',
                'description' => 'Journal Vouchers are sent to CAS/CGI to post BCEA payments to the General Ledger

Additional Information:
JV\'s for payments issued in MIS are created on the mainframe and sent to CAS using SFTP.
The JV\'s are sent in separate jobs, but all the feedback files are picked up in one job:
HADAJPHE -formats the JV\'s to be sent to CAS for the computer cheques just issued. 
HADAJPHF - formats the JV\'s to be sent to CAS for monthly computer cheque  reversals for the current fiscal year.  
HADAJPHR - formats the JV\'s to be sent to CAS for monthly EFT reversals for the current fiscal year.
HADAJPHT - formats the JV\'s to be sent to CAS for monthly EFT\'s just created. 
These jobs get are run on the first workday after cheque run on request - they have to be sent to CAS before the CAS cut off which I believe is late afternoon. However looking at historical the jobs, they usually run around lunchtime. 

HADAJPHG - format the JV\'s to be sent to CAS for the monthly computer cheque reversals for the  prior fiscal year.    
HADAJPHS - formats the JV\'s to be sent to CAS for monthly computer cheque  reversals for the previous fiscal year. 
For these two prior year reversal jobs, the scheduling is dependent on the results from the GL report created by job HMISJPHF (run in May-Mar).  In April job HMISJPHF is split into 3 jobs with job HMIS02HF producing the GL report at April monthend.

The following Wednesday  job HADAJPH4  is run to pick up the feedback reports for all the JV\'s sent earlier in the week. The feeback reports are pickedup using SFTP and then the reports are sent to office 013 inR2W (FASB)',
                'source_system' => 'MIS',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'First workday after cheque run weekend',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Post-Month Processing',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'BCEA Payments to BC Mail Plus
(Master Print File)',
                'short_description' => 'Payment details are sent to BC Mail Plus from MIS for printing, stuffing, and mailing in the Master Print File.',
                'description' => 'Payment details are sent to BC Mail Plus for printing, stuffing, and mailing.�Includes the office cheques followed by the sorted mail documents and formats them into a data print stream, inserting cheque and document numbers, insert information for the bottom of cheque, and other information',
                'source_system' => 'MIS',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Friday cutoff at 8:30 pm',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Payment Production',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'EFT Payments to Provincial Treasury',
                'short_description' => 'EFT payments for BCEA clients and suppliers are sent to Provincial Treasury from MIS for distribution to bank accounts',
                'description' => 'EFT payments for BCEA clients and suppliers are sent to Provincial Treasury for distribution to bank accounts.  No Response file.',
                'source_system' => 'MIS',
                'target_system' => 'Provincial Treasury',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Friday cutoff at 8:30 pm',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Payment Production',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Feedback from CAS/CGI
(JVs successful processed)',
                'short_description' => 'CAS/CGI provides feedback to MIS for Journal Vouchers indicating whether batches were processed successfully',
                'description' => 'CAS/CGI provides feedback indicating whether batches were processed successfully',
                'source_system' => 'CAS',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'The following Wednesday (after the Cheque run weekend)',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Post-Month Processing',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Family Bonus Request to CRA - Monthly',
                'short_description' => 'The Family Bonus Request to CRA (from MIS) interface for the Monthly Batch Job to update Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients',
                'description' => 'The Family Bonus Response to CRA interface involves sending the Master SIN List and Add/Delete Request File from the ministry to the Canada Revenue CRA for validation and processing.
The monthly batch will Request Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients.  (2 datasets)   Daily and monthly jobs are essentially the same.',
                'source_system' => 'MIS',
                'target_system' => 'CRA',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Evening of Tuesday of the week prior to BCEA cut off date (Approximately 10 days before cut-off)',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Pre-Month Processing',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Family Bonus Response from CRA - Monthly',
                'short_description' => 'The Family Bonus Response from CRA to MIS interface for the Monthly Batch Job to update Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients',
                'description' => 'Receive NCBS data from CRA for requested clients, which MIS uses to calculate the pseudo NCBS amount, update the FB tables, and adjust the client\'s Income 35 accordingly  (2 datasets).  Daily and monthly jobs are essentially the same.',
                'source_system' => 'CRA',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Evening of Thursday of the week prior to BCEA cut off date',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Pre-Month Processing',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Family Bonus Request to CRA - Daily',
                'short_description' => 'The Family Bonus Request to CRA (from MIS) interface for the Daily Batch Job to update Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients',
                'description' => 'The Family Bonus Response to CRA interface involves sending the Master SIN List and Add/Delete Request File from the ministry to the Canada Revenue CRA for validation and processing.
The monthly batch will Request Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients.  (2 datasets)   Daily and monthly jobs are essentially the same.',
                'source_system' => 'MIS',
                'target_system' => 'CRA',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Nightly',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Pre-Month Processing',
                    1 => 'FDD- Case Management INT',
                    2 => 'INT085',
                    3 => 'MIS',
                )
            ],
            [
                'name' => 'Family Bonus Response from CRA- Daily',
                'short_description' => 'The Family Bonus Response from CRA to MIS interface for the Daily Batch Job to update Family Bonus (35) and National Child Benefit supplement data for specific BCEA clients',
                'description' => 'Receive NCBS data from CRA for requested clients, which MIS uses to calculate the pseudo NCBS amount, update the FB tables, and adjust the client\'s Income 35 accordingly  (2 datasets).  Daily and monthly jobs are essentially the same.',
                'source_system' => 'CRA',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Nightly',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Pre-Month Processing',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Prism file from Canada Post',
                'short_description' => 'Canada Post forwards on a regular basis, updated Postal Codes to be used as input to our Postal Code database refresh job as the Prism file to MIS',
                'description' => 'Canada Post forwards on a regular basis, updated Postal Codes to be used as input to our Postal Code database refresh job. MIS maintains a table of valid postal codes (by city) to validate addresses e.g. entered in online transactions.',
                'source_system' => 'Canada Post',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => NULL,
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Pre-Month Processing',
                    1 => 'FDD- Case Management INT',
                    2 => 'MIS',
                )
            ],
            [
                'name' => 'Collection Letters to BC Mail Plus',
                'short_description' => 'Debt Collection letters for clients are sent from MIS to BC Mail Plus for printing',
                'description' => 'Letters to sent to clients indicating that collection activities have been transferred to RMS via Job JPB2.  Letters are not generated every day.  The letter file is printed directly at BC Mail Plus',
                'source_system' => 'MIS',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '6PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Accounts Receivable',
                    1 => 'Post-Month Processing',
                    2 => 'MIS',
                )
            ],
            [
                'name' => 'Bank Branch, Address from Provincial Treasury',
                'short_description' => 'Bank Branch, Bank Address and Nank Numbers Validations sent to MIS from Provincial Treasury',
                'description' => 'As part of bank validations,� the bank validation screen PYM and ICM Integration INT-081 calls a background transaction to Ministry of Finance/Provincial Treasury to Validate Bank Numbers and Branch Numbers, and get the bank address.  This is provided as part of weekly files containing bank number and branch number.',
                'source_system' => 'Provincial Treasury',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'weekly',
                'transaction_schedule' => 'No regular schedule',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT081',
                    1 => 'Misc MIS processes',
                    2 => 'MIS',
                )
            ],
            [
                'name' => 'Bank Account Validation from Provincial Treasury',
                'short_description' => 'Bank Account Number format Validation sent to MIS from Provincial Treasury as DOS module',
                'description' => 'As part of bank validations,� the bank validation screen PYM and ICM Integration INT-081 calls a background transaction to Ministry of Finance/Provincial Treasury to validate bank account number formats.  This is provided as a \'black-box program\' with account number formats.',
                'source_system' => 'Provincial Treasury',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['dos_module'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'other',
                'transaction_schedule' => 'No regular schedule',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT081',
                    1 => 'Misc MIS processes',
                    2 => 'MIS',
                )
            ],
            [
                'name' => 'Payment Details from RMS',
                'short_description' => 'RMS sends updates to the MIS system with payments and adjustments made to client debt',
                'description' => 'Transfers financial data regarding payments posted to accounts. Updates the MIS system with payments and adjustments made to client debt by RMS.',
                'source_system' => 'RMS',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'file_transfer',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'weekly',
                'transaction_schedule' => 'Part of weekly AR schedule',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Debt',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'A/R Details to RMS',
                'short_description' => 'MIS transfers RMS new, adjusted, or recalled accounts for processing debt collection.',
                'description' => 'Transfers new, adjusted, or recalled accounts to RMS for processing debt collection.',
                'source_system' => 'MIS',
                'target_system' => 'RMS',
                'mode_of_transfer' => 'file_transfer',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Part of daily AR schedule',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Debt',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'Cashed Cheques from Bank',
                'short_description' => 'Central 1 Credit Union sends MIS a file with details on cashed cheques that cleared the previous day, enabling reconciliation and status updates in the payment history data base',
                'description' => 'This interface transmits daily files containing details of cashed cheques from Central 1 Credit Union (C1CU) to the Vancouver reconciliation system. The file includes information on cheques that cleared the previous day, enabling reconciliation and status updates in the payment history data base.',
                'source_system' => 'Central 1 Credit Union',
                'target_system' => 'MIS',
                'mode_of_transfer' => 'file_transfer',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Each morning
Exact time not specified',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'Cheque Reconciliation?',
                    1 => 'MIS',
                )
            ],
            [
                'name' => 'INT683 - CPP Service Canada Request',
                'short_description' => 'Request file from ICM to Service Canada with SIN and DOB information of SDPR clients so Service Canada can match to clients in receipt of CPP Payment',
                'description' => 'To transfer SIN and Date of Birth information related to SDPR clients in ICM to Service Canada. Service Canada will use this information to send over CPP Payments received by matching clients back to ICM.',
                'source_system' => 'ICM',
                'target_system' => 'Service Canada',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Friday after the EA Payment Run and should be sent post business hours',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT683',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'CPP',
                    4 => 'Month-End Processing',
                    5 => 'AppGate',
                )
            ],
            [
                'name' => 'INT684 - CPP Service Canada Response',
                'short_description' => 'Response file from Service Canada based on previously received SIN/DOB data from INT683 providing information on SDPR clients receiving CPP Payments',
                'description' => 'To receive CPP Payment details from Service Canada based on the SIN/Date of Birth related file sent previously as part of the CPP Service Canada Request interface',
                'source_system' => 'Service Canada',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'First business day following the request file being sent to SC',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT684',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'CPP',
                    4 => 'Month-End Processing',
                    5 => 'AppGate',
                )
            ],
            [
                'name' => 'INT550 - ICM � CRA File Creation and Upload',
                'short_description' => 'Annual tax files sent from ICM to CRA for T5007 production',
                'description' => 'Once a year, once the T5007 data has been finalized and approved by FASB, files containing the tax data is created and uploaded to the CRA for each program area.  The following files will be created each year as part of the annual T5007 project.
�	Employment and Assistance T5007 data
�	Seniors Supplement T5007 data
�	Bus Pass Program - Seniors T5007 data
The files for each program area will be producible independently.',
                'source_system' => 'ICM',
                'target_system' => 'CRA',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'annually',
                'transaction_schedule' => '2nd to Last week of February',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT550',
                    1 => 'FDD- Case Management INT',
                    2 => 'FDD- Case Management  Appendix R INT',
                    3 => 'ICM INT',
                    4 => 'CRA',
                    5 => 'AppGate',
                )
            ],
            [
                'name' => 'INT640 - CRA Income Validation Request to CRA',
                'short_description' => 'Daily requests sent from ICM to CRA for Employment and Assistance cases (SDPR) to verify CRA income information matches income information in ICM for a particular Tax Year.',
                'description' => 'To request tax information from Canada Revenue Agency (CRA) for Employment and Assistance cases (SDPR) to verify CRA income information matches income information in ICM for a particular Tax Year.

Note: FDD has technical interface details (e.g. server address, file naming convention etc)',
                'source_system' => 'ICM',
                'target_system' => 'CRA',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'multiple_times_a_day',
                'transaction_schedule' => 'Unknown',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT640',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'CRA',
                )
            ],
            [
                'name' => 'INT641 - CRA Income Validation Response from CRA',
                'short_description' => 'Responses for INT640 from CRA to ICM providing income information related to the Contact for a particular tax year',
                'description' => 'This transaction will provide ICM with CRA tax information from Canada Revenue Agency (CRA) for Employment and Assistance cases (SDPR) related to the Contact for a particular tax year

Note: FDD has technical interface details (e.g. server address, file naming convention etc)',
                'source_system' => 'CRA',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'multiple_times_a_day',
                'transaction_schedule' => 'Unknown',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT641',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'CRA',
                )
            ],
            [
                'name' => 'INT319 - sendBusPassPrintToTranslink',
                'short_description' => 'Request from ICM to Translink to print new passes,  re-activate existing passes, cancel a pass, renew an existing pass after 20 years and provide a replacement pass in case of loss or damage',
                'description' => 'The BC Bus Pass program will leverage INT319 to send client�s Bus Pass request to TransLink.  Requests from ICM to Translink include: requests to print new passes,  re-activate existing passes, cancel a pass, renew an existing pass after 20 years and provide a replacement pass in case of loss or damage, through this interface.',
                'source_system' => 'ICM',
                'target_system' => 'Translink',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '11PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT319',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT320 - receiveBusPassPrintFromTranslink',
                'short_description' => 'Interface from Translink to ICM to update TransLink bus pass information in ICM (e.g. Pass #, PassIssueDate, and SerialNumber etc)',
                'description' => 'This interface will update TransLink bus pass information in ICM (e.g. Pass #, PassIssueDate, and SerialNumber etc)',
                'source_system' => 'Translink',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'multiple_times_a_day',
                'transaction_schedule' => '1AM|
2AM|
10AM|
11AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT320',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT312 - receivePaymentInfoFromHpas',
                'short_description' => 'This transaction will capture payment information from HP Advanced Solutions � Payment Processing Center (HPAS PPC), and will create payment record in ICM. In ICM, these payment records will be associated with relevant Bus Pass case.',
                'description' => 'This transaction will capture payment information from HP Advanced Solutions � Payment Processing Center (HPAS PPC), and will create payment record in ICM. In ICM, these payment records will be associated with relevant Bus Pass case.  HPAS-PPC acts as the Revenue Collection arm of the Bus Pass Program for the paper-based Cheque and In-Bank Payment Channels.',
                'source_system' => 'HPAS PPC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['dat'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'multiple_times_a_day',
                'transaction_schedule' => '5PM|
6PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT312',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT313 - receivePaymentInfoFromBank',
                'short_description' => 'Transaction from RBC to ICM capturing payments from a client (to banks) to pay down some amount owing for Bus Pass',
                'description' => 'Transaction from RBC to ICM capturing payments from a client (to banks) to pay down some amount owing for Bus Pass',
                'source_system' => 'RBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Unknown',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT313',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT307 - sendConfirmationToPublicTrustee',
                'short_description' => 'This transaction from ICM to PGT will send Client payment information to Public Guardian and Trustee of British Columbia (PGT) where Senior Supplement cases are administered by PGT.',
                'description' => 'This transaction will send Client payment information to Public Guardian and Trustee of British Columbia (PGT) where Senior Supplement cases are administered by PGT.',
                'source_system' => 'ICM',
                'target_system' => 'PGT',
                'mode_of_transfer' => 'batch',
                'data_format' => ['sent'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Senior Supplement Cheque Run Day',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT307',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'PGT',
                )
            ],
            [
                'name' => 'INT615 - sponsorshipDebtTransfertoGenTax',
                'short_description' => 'ICM sends GenTax (Min Finance) platform the amount of income assistance that has been paid to a ministry contact (Sponsoree) as part of the Sponsorship Debt Calculation process.',
                'description' => 'GenTax is the platform used to work in the Taxpayer Administration, Compliance & Services (TACS) system, a Ministry of Finance system that is used to track and manage debt from various programs across government. 

ICM will calculate the amount of income assistance that has been paid to a ministry contact (Sponsoree) as part of the Sponsorship Debt Calculation process.  The debt is calculated for each month and is based on the IA payments made to the sponsored contact and may be none, some or all of the IA payments made in a particular benefit month to the complete Case composition
ICM will send this information in XML format to GenTax, where it will be registered for collection from the Sponsor/Co-Sponsor.',
                'source_system' => 'ICM',
                'target_system' => 'GenTax',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => NULL,
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT615',
                    1 => 'FDD- Financials INT',
                    2 => 'TDD- Case Management INT',
                    3 => 'ICM INT',
                    4 => 'GenTax',
                    5 => 'Sponsorship',
                )
            ],
            [
                'name' => 'INT011 - Get BCeID Service Provider Info',
                'short_description' => 'This transaction will link the BCeID Authentication with ICM application Authorization and associate the Business DLUID to the Partner account.',
                'description' => 'This transaction will link the BCeID Authentication with ICM application Authorization and associate the Business DLUID to the Partner account. This step will copy the Business DLUID is associated to the Partner account. 
oSearch for an existing Service Provider record in BCeID system
oUpdate Service Provider Business DLUID (BCeID field) in ICM with the Service Provider identified in BCeID system

The integrations with BCeID system are SOAP based (Webservice call from WM to BCeID) and not file based.',
                'source_system' => 'BCeID',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'vbc_real_time_sync',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT011',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'BCeID',
                )
            ],
            [
                'name' => 'INT157 - Get BCeID Service Provider User Info',
                'short_description' => 'This transaction will display list of all BCeID user accounts (Employees) to the ICM Application Administrator available for the Partner Organization after performing a look up against the BCeID directory',
                'description' => 'This transaction will display list of all BCeID user accounts (Employees) to the ICM Application Administrator available for the Partner Organization after performing a look up against the BCeID directory
oSearch for BCeID user accounts associated with a Service Provider record in BCeID system
oAssociate BCeID user accounts with the Service Provider in ICM

The integrations with BCeID system are SOAP based (Webservice call from WM to BCeID) and not file based.',
                'source_system' => 'BCeID',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'vbc_real_time_sync',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT157',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'BCeID',
                )
            ],
            [
                'name' => 'INT338 - Get BCeID User Info',
                'short_description' => 'This transaction will display list of all BCeID user accounts based on the query sent to the BCeID directory from ICM',
                'description' => 'This transaction will display list of all BCeID user accounts based on the query sent to the BCeID directory

The integrations with BCeID system are SOAP based (Webservice call from WM to BCeID) and not file based.',
                'source_system' => 'BCeID',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'vbc_real_time_sync',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT338',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'BCeID',
                )
            ],
            [
                'name' => 'INT036 - CAS Supplier Download',
                'short_description' => 'This transaction is used to store all Service Provider records from CAS DW in a staging table within ICM and subsequently, selectively updating data into ICM service provider records.',
                'description' => 'This transaction is used to store all Service Provider records from CAS DW (Corporate Accounting Services Data Warehouse) in a staging table within ICM and subsequently, selectively updating data into ICM service provider records. CAS DW provides a download of all Service Providers on a nightly basis, which is used to populate the CAS staging table in ICM.  The file format is a fixed length text file.
The data in the staging table is used to:
oSearch for an existing Service Provider record before creating a new Service Provider in ICM. 
oUpdate Service Provider information in ICM with the latest updates done in CAS or by another system in CAS.  
oCopy CAS Service Provider in to ICM Service Provider by utilizing �Create New Vendor� functionality.
oLink CAS Service Provider to an existing ICM Service Provider by utilizing �Link to ICM� functionality. 
oDisplay latest CAS Service Provider�s address information, Payment method, Status, Originator, Name, and EFT Advice Preference.',
                'source_system' => 'CAS DW',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'database_integration',
                'protocol' => 'jdbc',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Tues-Sat|
3AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT036',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT400 - Create Service Provider Real Time',
                'short_description' => 'This transaction is used to send the newly created Service Provider information from ICM to CAS to create new Service Providers in CAS at real time.',
                'description' => 'This transaction is used to send the newly created Service Provider information from ICM to CAS (Corporate Accounting Services) to create new Service Providers in CAS at real time. This transaction supports creation of a new Supplier with a new Site and new EFT information.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT400',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT401 - Update Service Provider Real Time',
                'short_description' => 'This transaction is used to update Service Provider address, EFT Advice Preference, and email address from ICM to CAS to at real time.',
                'description' => 'This transaction is used to update Service Provider address, EFT Advice Preference, and email address from ICM to CAS to at real time. This transaction does not support EFT and name updates.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT401',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT403 - Reactivate Service Provider',
                'short_description' => 'This transaction is used to send a request to reactivate Service Provider in CAS at real time.',
                'description' => 'This transaction is used to send a request to reactivate Service Provider in CAS at real time. After the request has been submitted, CAS SDA (Security and Data Administration)  will manually validate and reactivate service provider in CAS.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT403',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT477 - Create New CAS Service Provider Site',
                'short_description' => 'This transaction is used to create a new site for an existing Service Provider in CAS at real time',
                'description' => 'In the scenario where a Service provider has a new office with a new address, a new site must be created in CAS. This transaction is used to create a new site for an existing Service Provider in CAS at real time. This transaction does not support creation of EFT information against a new site.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT477',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT478 - Retrieve CAS Service Provider Financial Information',
                'short_description' => 'This transaction will be used to retrieve Service Provider�s name, address, bank account information and email address from CAS.',
                'description' => 'This transaction will be used to retrieve Service Provider�s name, address, bank account information and email address from CAS. The data will be queried as needed from ICM to CAS',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'vbc_real_time_sync',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real-time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT478',
                    1 => 'FDD- Service Providers INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT006 - CAS Accounts Payable',
                'short_description' => 'This transaction is used to post Invoice data to CAS (from ICM) in order for a payment to be issued and register the expenditure in the accounting system.',
                'description' => 'Post Invoice data to CAS in order for a payment to be issued and register the expenditure in the accounting system.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '305 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT006',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT007-CAS CGI Feedback',
                'short_description' => 'Once Invoices are sent to CGI they are either processed or rejected at the Invoice level. The feedback transaction tells ICM the disposition of the Invoice load process at CAS.',
                'description' => 'Once Invoices are sent to CGI they are either processed or rejected at the Invoice level. The feedback transaction tells ICM the disposition of the Invoice load process at CAS.

This integration will be used by WebMethods to segregate feedback received for invoices and orders, and route for the respective internal integrations to ICM. 

ICM will use the following CAS CGI feeders as a part of this integration:
�3145 � SDPR Invoice Payments (Payments for SDPR Case Types: Employment and Assistance, Health Case, Temporary Case File)',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Following AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT007',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT007-3145-ACK - CAS CGI Feedback Acknowledgement',
                'short_description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file in INT306; using the 3145 SDPR Invoice Payments CGI Feeder.',
                'description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file. It will be generated once the files sent by ICM are picked up for CGI processing.
This will be a receipt/acknowledgement that the Inbox file has been received with a timestamp.
This file will be empty and all the information will be part of the filename. 
Manual intervention would be required from ICM Application Support if any errors are received as part of this Acknowledgement file. 
ICM will use the following CAS CGI feeders as a part of this integration:
�3145 � SDPR Invoice Payments (Payments for SDPR Case Types: Employment and Assistance, Health Case, Temporary Case File)',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'email',
                'data_format' => ['msg'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Following AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT007',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT010 - CAS Payment Maintenance',
                'short_description' => 'Payments are made from CAS for Invoices and On-demand EFTs approved and sent through Integrations. In order to know what payments have been issued the CAS DW will be queried for updated payment information and create payments where required.',
                'description' => 'Payments are made from CAS for Invoices approved and sent through INT006. In order to know what payments have been issued the CAS DW will be queried for updated payment information and create payments where required. In case an Invoice is cancelled or a Payment is voided the records will be updated accordingly.

Payments are made through INT409 CAS Account Payable for On-Demand EFT. In order to know what payments have been issued, the CAS DW will be queried for updated payment information and create payments where required. In case a Payment is voided the records will be updated accordingly.',
                'source_system' => 'CAS DW',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'database_integration',
                'protocol' => 'jdbc',
                'security' => [],
                'transaction_frequency' => 'custom',
                'transaction_schedule' => 'See FDD rules',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT010',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT152 - CAS DW Expense Auth Updates',
                'short_description' => 'To support the financial approval requirements ICM system needs to store employee�s approval rights in their employee record which will be updated from CAS DW on a daily basis and sent to ICM from an approval matrix stored in CAS DW.',
                'description' => 'To support the financial approval requirements ICM system needs to store employee�s approval rights in their employee record which will be updated from CAS DW on a daily basis.

The setup of Approval Requirements is mastered in Employees entity in ICM. After the approval matrix is updated in CAS DW, ICM will then pull the updated information from the data warehouse, based on Last Updated date of Employee table.

This integration is not file based, but WebMethods have scheduled job which runs from Mon-Sat 7:30 AM PST and picks the data from CAS DW(Datawarehouse) using JDBC connection.',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['sql'],
                'integration_type' => 'database_integration',
                'protocol' => 'jdbc',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Monday-Saturday 730 AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT152',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT305 - sendPaymentImageToCas',
                'short_description' => 'This transaction will process payments for clients on Senior Supplement Cases and Process refunds for client on Bus Pass Cases, sending information from ICM to CAS in orders to CAS Accounts Payable',
                'description' => 'This transaction will be leveraged by two program areas as described below:
-To process payments for the clients who are on Senior Supplement Case
-To process refunds for the clients who are on Bus Pass Case',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'custom',
                'transaction_schedule' => '330 AM daily (Bus Pass)|
5 PM ~20th of month; ~15th Dec (Senior Supplement)',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT305',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT306 - receivePaymentImageFeedbackFromCas',
                'short_description' => 'This feedback transaction tells ICM the disposition of the Order load process to CAS Invoice for orders sent to in INT305',
                'description' => 'This transaction will be leveraged by Senior Supplements and Bus Pass programs to receive CAS response sent as part of INT305 interface. After INT305 sends orders over to CAS Account Payable, the orders are either processed or rejected at the Order Level. The feedback transaction tells ICM the disposition of the Order load process to CAS Invoice',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'custom',
                'transaction_schedule' => 'In response to INT305',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT306',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT-306-3135-ACK - CAS CGI Feedback Acknowledgement',
                'short_description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file in INT306; using the 3135 Bus Pass CGI Feeder It will be generated once the files sent by ICM are picked up for CGI processing.',
                'description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file. It will be generated once the files sent by ICM are picked up for CGI processing.
This will be a receipt/acknowledgement that the Inbox file has been received with a timestamp.
This file will be empty and all the information will be part of the filename.
Manual intervention would be required from ICM Application Support if any errors are received as part of this Acknowledgement file. 
ICM will use the following CAS CGI feeders as a part of this integration:
�3135 � Bus Pass',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'email',
                'data_format' => ['msg'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => NULL,
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT306',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT-306-3140-ACK - CAS CGI Feedback Acknowledgement',
                'short_description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file in INT306; using the 3140 Senior Supplement CGI Feeder It will be generated once the files sent by ICM are picked up for CGI processing.',
                'description' => 'CAS will create an ACK file in the Outbox folder after the successful transfer of the Inbox file. It will be generated once the files sent by ICM are picked up for CGI processing.
This will be a receipt/acknowledgement that the Inbox file has been received with a timestamp.
This file will be empty and all the information will be part of the filename.
Manual intervention would be required from ICM Application Support if any errors are received as part of this Acknowledgement file. 
ICM will use the following CAS CGI feeders as a part of this integration:
�3140 � Seniors Supplements',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'email',
                'data_format' => ['msg'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => NULL,
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT306',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT409 - CAS Account Payable from Orders',
                'short_description' => 'This transaction will be leveraged by Employment and Assistance Program to process On-Demand EFT payments for Client and Service Provider from ICM to CAS',
                'description' => 'This transaction will be leveraged by Employment and Assistance Program to process On-Demand EFT payments for Client and Service Provider. The EFT On-Demand Payments will be processed through CAS Account Payable from ICM Orders.
ICM will use the following CAS CGI feeders as a part of this integration:
�3145',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '257 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT409',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT410 - Receive Order Payment Response from CAS',
                'short_description' => 'This transaction will be leveraged by Employment and Assistance Program to receive CAS EFT Payment response sent as part of INT409 interface. The feedback transaction tells ICM the disposition of the Order load process to CAS Invoice.',
                'description' => 'This transaction will be leveraged by Employment and Assistance Program to receive CAS EFT Payment response sent as part of INT409 interface. After an On-Demand EFT order is sent to CAS Account Payable, the orders are either processed or rejected at the Order Level. The feedback transaction tells ICM the disposition of the Order load process to CAS Invoice. 
ICM will use the following CAS CGI feeders as a part of this integration:
�3145',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '3:00 AM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT410',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT601 - sendPaymentRequestToCAS',
                'short_description' => 'This interface will be leveraged for ODP sent to CAS -  CAS will identify the Organization of the request by using the user ID of the transaction and authorize the expense authority and allow the order to update to Approved if successful.',
                'description' => 'This interface will be leveraged by Employment and Assistance program to send On Demand Cheque Payment Requests to CAS. This transaction will invoke the �CAS AP Webservice�. Orders sent to CAS are Expense Authorized in ICM.  CAS will identify the Organization of the request by using the user ID of the transaction and authorize the expense authority and allow the order to update to Approved if successful.',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['json'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real Time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT601',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT603 - receivePaymentDetailsfromCAS',
                'short_description' => 'This transaction will be leveraged by SDPR & MCFD programs to receive payment details such as Cheque # from CAS.',
                'description' => 'As part of On Demand Payments project, payment production and payment PDF functionality was be moved into CAS. ICM will integrate with CAS to request a payment and also to receive the payment details produced for the payment request. This transaction will be leveraged by SDPR & MCFD programs to receive payment details such as Cheque # from CAS.

ReceivePaymentDetailsfromCAS-INT603 integration is called by CAS through REST API call. CAS sends details in batches with approximately 15-20 min interval starting from 6:30 AM to 5:40 PM PST',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['pdf'],
                'integration_type' => 'api_rest',
                'protocol' => 'rest',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'CAS sends details in batches with approximately 15-20 min interval starting from 6:30 AM to 5:40 PM PST',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT603',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT606 - Receive Reconciled Payments from CAS',
                'short_description' => 'This interface will be leveraged for ODP sent to CAS - Once received, payment status in ICM will be updated and subsequent update integration transactions to MIS will be triggered',
                'description' => 'This transaction will be leveraged by Employment and Assistance program to receive Payment status updates from CAS for On Demand Cheque Payments. Once received, payment status in ICM will be updated and subsequent update integration transactions to MIS will be triggered.  Triggered Every 30 mins during working hours.',
                'source_system' => 'CAS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['csv'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'hourly',
                'transaction_schedule' => 'Hourly x 2',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT606',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT609 - Send VOID Payments To CAS',
                'short_description' => 'This interface will be leveraged for ODP payment voids to CAS.',
                'description' => 'This transaction will be leveraged by Employment and Assistance program to send payment void to CAS. This transaction will only be applicable for On Demand Cheque Payments',
                'source_system' => 'ICM',
                'target_system' => 'CAS',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_rest',
                'protocol' => 'rest',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real Time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT609',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'CAS',
                )
            ],
            [
                'name' => 'INT612 - Receive Void Request from MIS',
                'short_description' => 'This interface will be used by MCFD and SDPR programs to send Local cheque payment void payments (PRQs) from MIS to CAS through ICM.',
                'description' => 'This interface will be used by MCFD and SDPR programs to send Local cheque payment void payments (PRQs) from MIS to CAS through ICM. As MIS doesn�t support synchronous processing, wMethods will queue the payment requests received from MIS.

MIS sends the void request to ICM, which subsequently queues the request and triggers subsequent related void integrations (e.g. INT602, INT603) to CAS.

Note: Despite the naming in the FDD, Deloitte has confirmed this transaction is also used by SDPR.',
                'source_system' => 'MIS',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Real Time',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT612',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'ODP',
                    4 => 'MIS',
                    5 => 'CAS',
                )
            ],
            [
                'name' => 'INT310 - sendBusPassApplicationToBcMail',
                'short_description' => 'The purpose of this transaction is to send the Bus Pass applications to be printed and to be mailed to Clients. All the New/Renewal/Replacement applications will be sent to BC Mail Plus system',
                'description' => 'The purpose of this transaction is to send the Bus Pass applications to be printed and to be mailed to Clients. All the New/Renewal/Replacement applications will be sent to BC Mail Plus system',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '1030 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT310',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT311 - sendBusPassImmRenewalLetterToBCMail',
                'short_description' => 'The purpose of this transaction is to send a notice of assessment letter through BC Mail Plus, for eligible clients requesting them to provide additional information for the bus pass program.',
                'description' => 'The purpose of this transaction is to send a notice of assessment letter through BC Mail Plus, for eligible clients requesting them to provide additional information (Family Income), to avail the Bus Pass. BC Mail Plus receives the request from ICM and prints the letters and mails them to clients.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '11PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT311',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT416 - sendMSOEligibilityLetterToBCMail',
                'short_description' => 'The purpose of this transaction is to send letters (HR3317 HR3317B HR3644) through BC Mail Plus, for clients only eligible for Medical Services.',
                'description' => 'The purpose of this transaction is to send various letters through BC Mail Plus, for clients only eligible for Medical Services. BC Mail Plus receives the request from ICM and prints the letters and mails them to clients. Letters currently sent via this integration include:
�HR3317 MSO Eligibility - PWD Clients Letter
�HR3317B MSO Eligibility - PPMB Clients Letter
�HR3644 AEE Exhausted to MSO',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '11:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT416',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT473 - CTI PIN Send to BC Mail Plus',
                'short_description' => 'The purpose of this transaction is to send a letter through BC Mail Plus with client 3-digit IVR PIN details.',
                'description' => 'The purpose of this transaction is to send a letter through BC Mail Plus with client 3-digit IVR PIN details. BC Mail Plus receives the request from ICM and prints the letters and mails them to clients.

Note: this 3-digit IVR PID/PIN is different from the 4-digit PIN that the client self-creates for their BCeID and uses for MySS.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '11:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT473',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT501 - sendTHSEligibilityLetterToBCMail',
                'short_description' => 'The purpose of this transaction is to send a THS eligibility letter through BC Mail Plus, for clients only eligible for Transitional Health Services.',
                'description' => 'The purpose of this transaction is to send a THS eligibility letter through BC Mail Plus, for clients only eligible for Transitional Health Services. BC Mail Plus receives the request from ICM and prints the letters and mails them to clients.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '11:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT501',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT522 - sendBusPassStickerToBCMail',
                'short_description' => 'The purpose of this transaction is send the bus pass details (Contact Name and address details) to BC Mail Plus to for mailing of the Bus Pass Sticker to clients, via BC Mail.',
                'description' => 'The purpose of this transaction is send the bus pass details (Contact Name and address details) to BC Mail Plus to for mailing of the Bus Pass Sticker to clients, via BC Mail.  

Seniors and PWD clients are eligible for the BC Bus Pass Program.  In some transit regions (outside the TransLink system) in BC, a BC Transit annual validation sticker is required for to ride on BC transit.  New stickers get sent to eligible users each December, and every time the card gets replaced.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['lin'],
                'integration_type' => 'unknown',
                'protocol' => 'unknown',
                'security' => [],
                'transaction_frequency' => 'weekly',
                'transaction_schedule' => '1130 PM Monday',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT522',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                    4 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT552 - Send Tax Information To BCMailPlus',
                'short_description' => 'The system will create the following files each year for transfer to BC Mail Plus from ICM.',
                'description' => 'ICM � BC Mail Plus T5007 File Transfer
The system will create the following files each year for transfer to BC Mail Plus.
�Ad hoc test print files for each program area
�Employment and Assistance T5007 Non-NFA Print File
�Employment and Assistance T5007 NFA Print File
�Seniors Supplement T5007 Print File
�Seniors Bus Pass T5007 Print File
These files will be used for BC Mail Plus to print and mail T5007 slips to clients or deliver NFA slips to the office.
The file type is PSV (pipe delimited).',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['psv'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'annually',
                'transaction_schedule' => '2nd to Last week of February',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT552',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT672 -sendAOBFormToBCMailPlus',
                'short_description' => 'The Purpose of this transaction is to send the AOB form information to BC Mail Plus',
                'description' => 'The Purpose of this transaction is to send the AOB form information to BC Mail+. BC Mail+ receives the data file from ICM and prints the letters and mails them to offices.

The file format is .LIN, a flat file format used by BC Mail Plus.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['lin'],
                'integration_type' => 'messaging_queue',
                'protocol' => 'jms',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Part of the Cheque Run task post ICM Application job run.
Approx 630 AM Sat',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT672',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT675 - Send AEE Letters to BCMailPlus',
                'short_description' => 'The purpose of this transaction is to send Case and AEE information to BC Mail Plus to populate and send the exhausted correspondence and threshold letters, HR3508 and HR4040.',
                'description' => 'The purpose of this transaction is to send Case and AEE information to BC Mail Plus to populate and send the exhausted correspondence and threshold letters. More details below:
�Once a month, data is sent over from ICM to BC Mail+
�It contains data sent in a fixed length file to BC Mail+
�BC Mail+ hosts the templates for the letters
Letters currently sent via this integration include:
�HR3508 Annual Earnings Exemption (25 Percent Remaining) Threshold Letter
�HR4040 Annual Earnings Exemption (0 Percent Remaining) Threshold Letter
The file format is .LIN, a flat file format used by BC Mail Plus.',
                'source_system' => 'ICM',
                'target_system' => 'BC Mail Plus',
                'mode_of_transfer' => 'batch',
                'data_format' => ['lin'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Friday the week after EA Payment Run, after business hours',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT675',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT696 - ScanFax Attachment from BCMailPlus',
                'short_description' => 'The purpose of this transaction is to provide BC Mail and MPDP with a network of folders that they can scan and transfer documents into ICM via file transfers',
                'description' => 'All existing scanned and faxed documents are currently placed into specific network folders before being sent to ICM.   These same folders are expected to be utilized for new document transfer to ICM.  These network folders correspond with offices at the top level however they may be subdivided further for specific types of documents within an office.  ICM will use the network folder to pass on information for use in document profiling.   

BC Mail and Mail, Payment and Document Processing (MPDP) also have the ability to scan documents into ICM in the Attachment tab for all existing options, based on availability in our Multi-Function Device (MFD) office scanners.   They scan using their own MFDs, and transfer the files to the specified folders using a daily sFTP.',
                'source_system' => 'BC Mail Plus',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['pdf'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '6:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT696',
                    1 => 'FDD- Attachments INT',
                    2 => 'ICM INT',
                    3 => 'BC Mail Plus',
                )
            ],
            [
                'name' => 'INT696/INTM1.3TX03 - ScanFax Attachment from BCMailPlus - BP',
                'short_description' => '',
                'description' => 'All existing scanned and faxed documents are currently placed into specific network folders before being sent to ICM.   These same folders are expected to be utilized for new document transfer to ICM.  These network folders correspond with offices at the top level however they may be subdivided further for specific types of documents within an office.  ICM will use the network folder to pass on information for use in document profiling.   

BC Mail and Mail, Payment and Document Processing (MPDP) also have the ability to scan documents into ICM in the Attachment tab for all existing options, based on availability in our Multi-Function Device (MFD) office scanners.   They scan using their own MFDs, and transfer the files to the specified folders using a daily sFTP.

This scanning process is specific for the Bus Pass folder',
                'source_system' => 'BC Mail Plus',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['pdf'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '6:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT696',
                    1 => 'INTM1.3TX03',
                    2 => 'FDD- Attachments INT',
                    3 => 'ICM INT',
                    4 => 'BC Mail Plus',
                    5 => 'Bus Pass',
                )
            ],
            [
                'name' => 'INT696/INTM1.3TX03 - ScanFax Attachment from BCMailPlus - PWD',
                'short_description' => '',
                'description' => 'All existing scanned and faxed documents are currently placed into specific network folders before being sent to ICM.   These same folders are expected to be utilized for new document transfer to ICM.  These network folders correspond with offices at the top level however they may be subdivided further for specific types of documents within an office.  ICM will use the network folder to pass on information for use in document profiling.   

BC Mail and Mail, Payment and Document Processing (MPDP) also have the ability to scan documents into ICM in the Attachment tab for all existing options, based on availability in our Multi-Function Device (MFD) office scanners.   They scan using their own MFDs, and transfer the files to the specified folders using a daily sFTP.

This scanning process is specific for the PWD folder',
                'source_system' => 'BC Mail Plus',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['pdf'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => '6:00 PM',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT696',
                    1 => 'INTM1.3TX03',
                    2 => 'FDD- Attachments INT',
                    3 => 'ICM INT',
                    4 => 'BC Mail Plus',
                    5 => 'PWD',
                )
            ],
            [
                'name' => 'INT407 - CTI Client Authentication',
                'short_description' => 'This transaction will be used to authenticate a client calling through the Interactive Voice Recording (IVR) system based on the PID/PIN or Sin information provided.',
                'description' => 'This transaction will be used to authenticate a client calling through the Interactive Voice Recording (IVR) system based on the PID/PIN or Sin information provided.',
                'source_system' => 'IVR/ICE',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT407',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'ICE',
                    4 => 'IVR',
                )
            ],
            [
                'name' => 'INT408 - CTI Reset PIN Management',
                'short_description' => 'This transaction will allow clients to reset their PIN automatically by calling through the IVR system or manually with the help of a call center agent.',
                'description' => 'This transaction will allow clients to reset their PIN automatically by calling through the IVR system or manually with the help of a call center agent.',
                'source_system' => 'IVR/ICE',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT408',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'ICE',
                    4 => 'IVR',
                )
            ],
            [
                'name' => 'INT414 - CTI Get Financial Info from ICM',
                'short_description' => 'This transaction will allow clients to access their case and financial information automatically by calling through the IVR system or manually with the help of a call center agent by just using PID and PIN information.',
                'description' => 'This transaction will allow clients to access their case and financial information automatically by calling through the IVR system or manually with the help of a call center agent by just using PID and PIN information.',
                'source_system' => 'IVR/ICE',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT414',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'ICE',
                    4 => 'IVR',
                )
            ],
            [
                'name' => 'INT630 - CTI Client Additional Information',
                'short_description' => 'This transaction will be used to send additional information after client authenticates through the IVR system and selects Monthly Report.',
                'description' => 'This transaction will be used to send additional information after client authenticates through the IVR system and selects Monthly Report.',
                'source_system' => 'IVR/ICE',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT630',
                    1 => 'FDD- Contacts INT',
                    2 => 'ICM INT',
                    3 => 'IVR',
                )
            ],
            [
                'name' => 'INT611 - Send Print Request To Server',
                'short_description' => 'This integration will enable ICM system to send print requests to MPS print servers for printing ODP cheques.',
                'description' => 'This interface will be leveraged by SDPR & MCFD programs to print On Demand Cheques. SDPR & MCFD office workers initiate printing of the On Demand Cheques from within ICM system. This integration will enable ICM system to send print requests to MPS print servers.

Office workers can then go to the cheque printer in the office and release print jobs initiated from the ICM system. Before releasing the print jobs, worker will load cheque stock into the printer tray. Once the print jobs are released by worker, cheques will be printed by the worker on the loaded cheque stock.

This transaction is Real Time (LPD/LPR Protocol Based).  LPD/LPR is a network printing protocol used for submitting print jobs to a remote printer, with LPR (Line Printer Remote) sending the request and LPD (Line Printer Daemon) receiving and processing it, typically over TCP/IP and port 515.',
                'source_system' => 'ICM',
                'target_system' => 'MPS Print Servers',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT611',
                    1 => 'FDD- Financials INT',
                    2 => 'ICM INT',
                    3 => 'ODP',
                    4 => 'MPS',
                )
            ],
            [
                'name' => 'INT301 - OAS and IA File Download from Service Canada',
                'short_description' => 'This integration will retrieve the monthly files of OAS file and International Operations file from Service Canada, for use by the Senior Supplement program',
                'description' => 'This integration will retrieve the monthly files of OAS file and International Operations file from Service Canada.  These two files that are received from Service Canada and used by the Seniors Supplement program.  The files are downloaded by ICM App Support via Appgate running batch server process.

These files will later be processed by ICM Team for assessing the eligibility of the clients to receive the OAS/GIS Benefits for that month.',
                'source_system' => 'Service Canada',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => '6PM Service Canada generates the IO file on the first Monday of the month and the OAS file usually on the 3rd Monday of the month. ICM operators will then work on downloading the files when the notification email has been sent on the file availability.',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT301',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'OAS',
                    4 => 'GIS',
                    5 => 'AppGate',
                )
            ],
            [
                'name' => 'INT548 - Send T5007 Information To MYSS',
                'short_description' => 'This integration will allow the MySS client portal to retrieve T5007 information from ICM real-time.',
                'description' => 'This integration will allow the MySS client portal to retrieve T5007 information from ICM real-time. Up-to-date versions of T5007 slips from the past 6 years will be available real-time.',
                'source_system' => 'Client Portal',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT548',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'T5007',
                )
            ],
            [
                'name' => 'INT538 - ICM Medical Services Plan',
                'short_description' => 'ICM integrates with Health Insurance BC (HIBC) for enrollments/cancellations in Medical Services Plan (MSP) and drug coverage through the Pharma Care and Fair Pharma Care for the ministry clients.',
                'description' => 'ICM integrates with Health Insurance BC (HIBC) for enrollments/cancellations in Medical Services Plan (MSP) and drug coverage through the Pharma Care and Fair Pharma Care for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for MSP and drug coverage with HIBC.  The integration will also receive enrollment status from HIBC to synchronize data in ICM system.',
                'source_system' => 'ICM',
                'target_system' => 'HIBC',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT538',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'HIBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT540 - HIBC Status Integration',
                'short_description' => 'This integration will receive enrollment status from MoH sftp server to synchronize data in ICM system.',
                'description' => 'This integration will receive enrollment status from MoH sftp server (ftpsvcs.hlth.gov.bc.ca aka Yoshi) to synchronize data in ICM system.',
                'source_system' => 'HIBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT540',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'HIBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT546 - Send Baby Enrollment Records To HIBC',
                'short_description' => 'This integration will send newborn dependent enrollment records from ICM to HIBC',
                'description' => 'This integration will send newborn dependent enrollment records from ICM to HIBC',
                'source_system' => 'ICM',
                'target_system' => 'HIBC',
                'mode_of_transfer' => 'batch',
                'data_format' => ['csv'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'No regular schedule',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT546',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'HIBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT539 - ICM Query Coverage Information',
                'short_description' => 'SDPR or MCFD workers send HIBC On Demand inquiries of MSP Account, Person, and Coverage information.',
                'description' => 'This transaction involves SDPR and MCFD sending inquiries of MSP Account, Person, and Coverage information.  The SDPR/MCFD Case Worker performs a query of SDPR/MCFD clients by using the Integrated Case  Management.  

Requests are on demand and can be submitted at any time during business hours.  Requests can be submitted after business hours (for query transaction only) and a response will be returned if the RAPID system is available (i.e. RAPID is not offline for a scheduled business or maintenance outage).  (ICM) system to send a request to the MSP Enrolment System.

Volume estimate is approximately 2000-3000 inquiries per day.',
                'source_system' => 'ICM',
                'target_system' => 'HIBC',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'Requests are on demand and can be submitted at any time during business hours.  Requests can be submitted after business hours (for query transaction only) and a response will be returned if the RAPID system is available (i.e. RAPID is not offline for a scheduled business or maintenance outage).',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT539',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'HIBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT547 - receive Healthy Kids Enrollment File from HIBC',
                'short_description' => 'This batch integration will receive the list of children on MSP Supplementary Benefits for enrollment in Healthy Kids.',
                'description' => 'This batch integration will receive the list of children on MSP Supplementary Benefits (formerly MSP Premium Assistance) for enrollment in Healthy Kids. (Enrollment is carried out by renaming and re-encrypting the file, then sending on to Pacific Blue Cross.',
                'source_system' => 'HIBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['psv'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ssh',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Last business day of the month minus 3 days',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT547',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'HIBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT541 - Extended Health Plan Request',
                'short_description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments',
                'description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments/cancellations in extended health coverage for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for extended health coverage with PBC system.  The integration will also receive enrollment statements, error file, and monthly report files from PBC to synchronize data in ICM system.',
                'source_system' => 'ICM',
                'target_system' => 'PBC',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Daily',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT541',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT542 - Extended Health Plan Response',
                'short_description' => 'ICM integrates with Pacific Blue Cross (PBC) fto receives responses to client enrollments/updates',
                'description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments/cancellations in extended health coverage for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for extended health coverage with PBC system.  The integration will also receive enrollment statements, error file, and monthly report files from PBC to synchronize data in ICM system.',
                'source_system' => 'PBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Daily',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT542',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT543 - Extended Health Plan Update Request',
                'short_description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollment updates and cancellations',
                'description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments/cancellations in extended health coverage for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for extended health coverage with PBC system.  The integration will also receive enrollment statements, error file, and monthly report files from PBC to synchronize data in ICM system.',
                'source_system' => 'ICM',
                'target_system' => 'PBC',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'Daily',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT543',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT544 - Monthly Reconciliation to PBC',
                'short_description' => 'ICM integrates with Pacific Blue Cross (PBC) and receives a monthly reconciliation report file',
                'description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments/cancellations in extended health coverage for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for extended health coverage with PBC system.  The integration will also receive enrollment statements, error file, and monthly report files from PBC to synchronize data in ICM system.
The',
                'source_system' => 'PBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'Monthly',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT544',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT547 - send Healthy Kids Enrollment File from HIBC',
                'short_description' => 'ICM connects to PBC via SFTP protocol to send the monthly Healthy Kids enrollment file.',
                'description' => 'ICM connects to PBC via SFTP protocol to send the monthly Healthy Kids enrollment file.

The Healthy Kids transaction is specific to SDPR and runs once per month.',
                'source_system' => 'ICM',
                'target_system' => 'PBC',
                'mode_of_transfer' => 'batch',
                'data_format' => ['txt'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'monthly',
                'transaction_schedule' => 'The Healthy Kids Enrollment File will be sent to PBC on the last business day of the month less two business days.',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT547',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT554 - EH Benefit Data From PBC (Extended Health)',
                'short_description' => 'ICM integrates with Pacific Blue Cross (PBC) for data updates about extended health files',
                'description' => 'ICM integrates with Pacific Blue Cross (PBC) for client enrollments/cancellations in extended health coverage for the ministry clients.
This integration will allow workers to manage client new enrollments/update enrollments/cancel enrollments /other data updates for extended health coverage with PBC system.  The integration will also receive enrollment statements, error file, and monthly report files from PBC to synchronize data in ICM system.',
                'source_system' => 'PBC',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'batch',
                'data_format' => ['xml'],
                'integration_type' => 'file_transfer',
                'protocol' => 'ftp',
                'security' => [],
                'transaction_frequency' => 'daily',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT554',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'PBC',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT536 - EMPI Find Candidates',
                'short_description' => 'The purpose of this interface is to send the enrollment details to EMPI.',
                'description' => 'The Enterprise Master Patient Index (EMPI) provides highly sophisticated probabilistic searching and matching capabilities in order to provide a source-of-truth for client demographics in situations, such as in B.C, where multiple instances of client records exist in disparate information systems.  Identity information will be provided by connected systems and, where these are probabilistically identified, as being for the same individual, will be linked to form sets representing a single identity.

The purpose of this interface is to send the enrollment details to EMPI.

This transaction would be initiated in Siebel. ICM client identification (e.g. registration) processes and systems are integrated with the EMPI. The EMPI is searched for client identity. If found, then the ICM system is loaded with the identity information from the EMPI. If not found, the client is added as a new identity. The new client identity is sent to the EMPI as a downstream (or batch) process. Sometimes known as front-end integration or active search.

Note: this is a different transaction from INT536 - ICM Personal Health Identification (the response integration).',
                'source_system' => 'ICM',
                'target_system' => 'EMPI',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT536',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'EMPI',
                    4 => 'MSP',
                )
            ],
            [
                'name' => 'INT536 - ICM Personal Health Identification',
                'short_description' => 'The purpose of this interface is to receive PHN details from EMPI',
                'description' => 'The Enterprise Master Patient Index (EMPI) provides highly sophisticated probabilistic searching and matching capabilities in order to provide a source-of-truth for client demographics in situations, such as in B.C, where multiple instances of client records exist in disparate information systems.

This transaction is primarily a VBC-related transaction. There are multiple parts to this including Search by Name, Search by PHN, Search by Address, Select PHN, New PHN and Merge Contact. At a high level the transactions are as follows:
�	Search by Name � Searching of records in EMPI using Name
�	Search by PHN � Searching of records in EMPI using PHN
�	Search by Address � Searching of records in EMPI using Address
�	Select PHN � Selection of PHN sent  by EMPI
�	New PHN � Requesting new PHN from EMPI
�	Merge Contact � Sending details of Merge in ICM to EMPI

Note: this is a different transaction from INT536 - EMPI Find Candidates (the request integration)',
                'source_system' => 'EMPI',
                'target_system' => 'ICM',
                'mode_of_transfer' => 'real_time',
                'data_format' => ['xml'],
                'integration_type' => 'api_soap',
                'protocol' => 'soap',
                'security' => [],
                'transaction_frequency' => 'on_demand',
                'transaction_schedule' => 'On Demand',
                'complexity' => 'high',
                'tags' => array(
                    0 => 'INT536',
                    1 => 'FDD- Case Management INT',
                    2 => 'ICM INT',
                    3 => 'EMPI',
                    4 => 'MSP',
                )
            ],
        ];

        $this->createInterfaces($interfaces);
    }

    private function createInterfaces(array $interfaces): void
    {
        foreach ($interfaces as $interfaceData) {
            $sourceSystem = BoundarySystem::where('name', $interfaceData['source_system'])->first();
            $targetSystem = BoundarySystem::where('name', $interfaceData['target_system'])->first();

            if (!$sourceSystem || !$targetSystem) {
                continue;
            }

            $interface = BoundarySystemInterface::create([
                'name' => $interfaceData['name'],
                'short_description' => $interfaceData['short_description'],
                'description' => $interfaceData['description'],
                'source_system_id' => $sourceSystem->id,
                'target_system_id' => $targetSystem->id,
                'mode_of_transfer' => $interfaceData['mode_of_transfer'],
                'data_format' => $interfaceData['data_format'],
                'integration_type' => $interfaceData['integration_type'],
                'protocol' => $interfaceData['protocol'],
                'security' => $interfaceData['security'],
                'transaction_frequency' => $interfaceData['transaction_frequency'],
                'transaction_schedule' => $interfaceData['transaction_schedule'],
                'complexity' => $interfaceData['complexity']
            ]);

            // Attach tags
            foreach ($interfaceData['tags'] as $tagName) {
                $tag = BoundarySystemTag::where('name', $tagName)->first();
                if ($tag) {
                    $interface->tags()->attach($tag->id);
                }
            }
        }
    }
}
