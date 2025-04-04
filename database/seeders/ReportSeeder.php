<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Report;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        \DB::table('reports')->delete();

        \DB::table('reports')->insert(array(

            array(
                'id' => 51,
                'name' => 'Computer Cheques and EFTs Issued (MI116-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'List of Cheques issues in XXX month. All cheques issued in the month, sorted by local office, including all distrubution methods. 
Report includes Cheque Payment to: Client, Supplier, Directed to office, Signal, No Stub
',
            ),

            array(
                'id' => 52,
                'name' => 'MSO MONITORING REPORT (MI95W-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - Changes to MSO Status that require worker Action. 
Lists all MSO files in the region
Purpose: Used by office 313 only to remind MSO clients to submit sd81 for AEE rollover 
Case Status: Open
Class Code: 08
PWD Status: Yes
Enhancement- Add Criteria of MSO Reaon: AEE Exhausted',
            ),

            array(
                'id' => 53,
                'name' => 'BCEA Computer Cheques Requiring Review (p071-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - Payments directed to office for >$4500 or invalid PCD. Field corrects the cases so payments are not directed to local office. Report run as part of pre-cheque issue',
            ),

            array(
                'id' => 54,
                'name' => 'Gain-Family Bonus (FB) Discrepancies (MI08U-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - Family Bonus Discrepancy. Comparson of Service Canada to EA Cases which reports number of dependents on a case that are eligilbe for FB top up 
',
            ),

            array(
                'id' => 55,
                'name' => 'BCEA DEBT REPORT (MI361-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Sponsorship',
            ),

            array(
                'id' => 56,
                'name' => 'MI37F-01 - Sponsorship Receivables Collectio Details of Debt Calculation (By Person)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Sponsorship',
            ),

            array(
                'id' => 57,
                'name' => 'MI37F-02 - Sponsorship Receivables Collectio Summary of Debt Calculation (By Person)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Sponsorship',
            ),

            array(
                'id' => 58,
                'name' => 'Financial Worker Field Advice (MI137-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - case changes after monthly Cheq Issue where there may be financial eligibilty impacts. Changes as a result of monthly Anniversary Process. Multiple \'Advice Messges\' generated on the report, but Legacy ID. Report needs updating as many Advice Messages are obsolete. Report organized by Office Number then by Caseload Type EMP, HOM, LTC, MSO, NEO, OUT, PMB, PWD, TPA',
            ),

            array(
                'id' => 59,
                'name' => 'Financial Worker Review Report (MI137-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management -Worker Review Message - case changes after monthly Cheq Issue - Various \'Review Messages\' are generated as a result of Anniversary Process changes to case. This requires review by the field as many \'Review Messages\' are obsolete.',
            ),

            array(
                'id' => 60,
                'name' => 'CPP Income Load - Case Review List (MI59H-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'CPP Income - File from Service Canada matches to case and case financial data. CPP team review for action or changes on cases',
            ),

            array(
                'id' => 61,
                'name' => 'Open GAIN Clients Missing SINs Report (MI579-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Obslete- Replaced by SIN Multiple Person Report',
            ),

            array(
                'id' => 62,
                'name' => 'SINMultiplePersonsReport',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Generated from CDW and replaces Open GAIN Clinets Missin SINS Report',
            ),

            array(
                'id' => 63,
                'name' => 'UnsubmittedChartReport (OPC001-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Overpayment Calculator - Unsubmitted Over Payment Reports. OPC pulls financial and payment information from MIS for calculation of Overpayment. OPC is manally complted by FASB. Debt is then manuyally added to case.',
            ),

            array(
                'id' => 64,
                'name' => 'AR Collections - Accounts Sent to BCMail (MI94K-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB - AR Collections letter to BC Mail',
            ),

            array(
                'id' => 65,
                'name' => 'AR Collections - Data Rejected for Transfer to Collection (MI94K-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB- AR data rejected for transfer to Collections FASB - Data Rejected for Transfer to Collections',
            ),

            array(
                'id' => 66,
                'name' => 'AR Collections - Data Sent for Collection (MI94K-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB - list of cases and debt sent to AR for Collections',
            ),

            array(
                'id' => 67,
                'name' => 'Daily Unallocated Payments - MI766-02',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Daily Unallocated Payments - Ask FASB for expanded Description',
            ),

            array(
                'id' => 68,
                'name' => 'MI212-01 - HISTORY REPORT',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'History Report- Ask Fasb for expanded Description',
            ),

            array(
                'id' => 69,
                'name' => 'Overpayment Monitoring Report (MI54K-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Overpayment list by Legacy ID, record of payment made towards debt, overpayment current amount and any Refund or Adjustment owing due to client over-paying debt amount. Includeds data on refund issued and refund date.',
            ),

            array(
                'id' => 70,
                'name' => 'RMS To Gain Repayment Upload Error Report (MI10B-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'RMS report = Discrepancy in Debt amount between systems',
            ),

            array(
                'id' => 71,
                'name' => 'GL Summary For Daily FMIS To MIS Data Transfer (MI07H-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'General ledger summary for weekly RMS to MIS data transfer',
            ),

            array(
                'id' => 72,
                'name' => 'MIS Debt Select-Extract Control Totals (MI26K-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Operhlp to run the HMISJPRJ final approval report with instructions on which debt criteria to be included',
            ),

            array(
                'id' => 73,
                'name' => 'MIS Debt Select-Extract Job Selection Criteria (MI20V-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB report with manual criteria for report data inclusion. Dollar range, Deby type. Operhlp to run the HMISJPRJ final approval report with instructions on which debt criteria to be included',
            ),

            array(
                'id' => 74,
                'name' => 'MIS Debt Select-Extract Outstanding Balances By Type (MI26K-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Outstanding debt balance, list of case CLOSED cases
Operhlp to run the HMISJPRJ final approval report with instructions on which debt criteria to be included',
            ),

            array(
                'id' => 75,
                'name' => 'Total Debt Statistics for Province (MI59D-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Total summarized by Debt Amount and Debt Code',
            ),

            array(
                'id' => 76,
                'name' => 'GAIN AR General Ledger Journal Voucher Summary (MI07K-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'AR General Ledger Summarty',
            ),

            array(
                'id' => 77,
                'name' => 'BCCCU - EXRD (AMP)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Automated Transfer System Detailed Listing
Daily banking- what has been processed and returend with Central 1 the previous day through
ORS - Online Return System -
For On Demand Payment - Not Monthly Cheques
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic',
            ),

            array(
                'id' => 78,
                'name' => 'ODP BCCCU/BCCCU Monthly Charges For Canadian Account (AMP)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Bank Statement 
Daily Journal from Central 1 Banking - summary of Debit and Credits',
            ),

            array(
                'id' => 79,
                'name' => 'LIST OF REPAYMENT ADJUSTMENT (MI07J-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Report of adjustment by Case and worker name who created the manual Increase or Decrease to Debt. Total $ value of adjustments by worker MIS ID. Reconcilliation of accounts of deposits and paymwent applied to the debt.',
            ),

            array(
                'id' => 80,
                'name' => 'Control Totals Report (MI94J-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Report of adjustment by Case and worker name who created the manual Increase or Decrease to Debt. Total $ value of adjustments by worker MIS ID. Reconcilliation of accounts of deposits and paymwent applied to the debt.',
            ),

            array(
                'id' => 81,
                'name' => 'Daily Cancelled Cheque Activity - (MI766-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Daily report of Cancelled cheque activity (OPD cheques)
',
            ),

            array(
                'id' => 82,
                'name' => 'Cheque Reconciliation - Cashed Summary Report (MI55U-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Total Cheque amounts issued and amt cleared (frequency un-know)',
            ),

            array(
                'id' => 83,
                'name' => 'Cheque Reconciliation Daily Cash Mismatch (MI92T-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Daily list of cashed cheque Misspatch reason',
            ),

            array(
                'id' => 84,
                'name' => 'Daily CUCBC Cashed Cheques Endlist Report (MI55U-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Data compared with Central 1 banking',
            ),

            array(
                'id' => 121,
                'name' => 'Control Totals for GL Journal Entries JPJ1 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly report. Comparison with JV report. Supplier payments - Ask Fasb for extended description',
            ),

            array(
                'id' => 122,
                'name' => 'Exception Report - ODCHQ (MI03S-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'TBD - to be reviewed',
            ),

            array(
                'id' => 123,
                'name' => 'Cheque Reconciliation Reversals - ODCHQ (MI04R-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Reversed cheques by Chq number, date, amount and other dtails',
            ),

            array(
                'id' => 85,
                'name' => 'BCCCU - EXRD',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'CUBC Online Returns Processed Items - Report from Daily Central 1 banking data 
Daily banking- what has been processed and returend with Central 1 the previous day through
ORS - Online Return System -
For Computer Cheques Only
List payments chashed on the day prior 
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic
',
            ),

            array(
                'id' => 86,
                'name' => 'BCCCU Monthly Charges For Canadian Account',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Central 1 Canadial Banking System Daily Journal
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic',
            ),

            array(
                'id' => 87,
                'name' => 'BCCCU Account Activity And Balances',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Central 1 Banking - Activity and Balance 
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic',
            ),

            array(
                'id' => 88,
                'name' => 'Accepted Transactions Report - MI10B-03',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Changes to Debt on Case- Increase, Decrease and balance',
            ),

            array(
                'id' => 89,
                'name' => 'Control Report - MI10B-02',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Contol Report Toals related to Debt',
            ),

            array(
                'id' => 90,
                'name' => 'CAS-CGI Interface - Summary (FMPH8-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Batches posted to CAS - total by batch number and Journam Voucher',
            ),

            array(
                'id' => 91,
                'name' => 'AFT Payment Release System - Payment Release Control Report (F08AFT10-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Minister of Finance - List of all EFT payments for XXX month',
            ),

            array(
                'id' => 92,
                'name' => 'BCEA COMPUTER PAYMENTS OVER (MI98C-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Payments over $4000 - Produced Monthly at during cheque run',
            ),

            array(
                'id' => 93,
                'name' => 'Cheque Reconciliation Outstanding List (MI04E-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'list of all outstanding cheque as of date report is pulled',
            ),

            array(
                'id' => 94,
                'name' => 'GENERAL LEDGER JV REPORT (MI96H-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'list of all outstanding cheque as of date report is pulled',
            ),

            array(
                'id' => 95,
                'name' => 'CHEQUE ALLOWANCE BANDING TOTALS SPREADSHEET (MI588-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'list of all outstanding cheque as of date report is pulled',
            ),

            array(
                'id' => 96,
                'name' => 'Cheque Reconciliation Control Totals By Account (MI04A-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Total cheque total by banks account; total by cheque status',
            ),

            array(
                'id' => 97,
                'name' => 'Cheque Totals By Allowance Codes (MI37Z-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Cheque total by allowance code= total $ issued by each allowance Code',
            ),

            array(
                'id' => 98,
                'name' => 'MI97I-02 - IMMEDIATE EFT TOTALS BY ALLOWANCE CODES',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly EFT issued- Totals by Allowace code',
            ),

            array(
                'id' => 99,
                'name' => 'MI97I-01 - IMMEDIATE EFT TOTALS BY ALLOWANCE CODES - EXPENDITURES',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly EFT - sorted by Case Class and totalled by Product total',
            ),

            array(
                'id' => 100,
                'name' => 'Family Bonus Analysis - Hardship (MI10Q-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Family Bonus Discrepancy by Category',
            ),

            array(
                'id' => 101,
                'name' => 'F.I.C.S. Account Listing JPJ7 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Finacial Information Control System - Account Lisin',
            ),

            array(
                'id' => 102,
                'name' => 'Control Totals for GL Journal Entries JPJ7 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Control Totals for GL for MONTH sorted by cheque type',
            ),

            array(
                'id' => 103,
                'name' => 'F.I.C.S. Account Listing EFT 02J7 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FICS by EFT',
            ),

            array(
                'id' => 104,
                'name' => 'Control Totals for GL Journal Entries EFT 02J7 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Control totals for yw-eft GL Journal entries. Selected month, up to run date.',
            ),

            array(
                'id' => 105,
                'name' => 'MI29S-01 - POSTAL CODE DISTRIBUTION BY DELIVERY STANDARD',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'POSTAL CODE DISTRIBUTION BY DELIVERY STANDARD',
            ),

            array(
                'id' => 106,
                'name' => 'SUMMARY OF CHEQUES, EFT PAYMENTS AND MESSAGES (MI97C-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Summary of Cheques, EFT Payments and Messages for XXX month. 
Total amounts and number of payments for each of : Cheques, Client Mail EFTs, Total Supplier EFTs, Total Payments, for the month',
            ),

            array(
                'id' => 107,
                'name' => 'CLIENT NON-MAIL SUPPLIER EFT PAYMENT SUMMARY (MI56J-04)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly run totals for eft pymts. Total by EFT issued and number of payments, sorted by EFT notice to client (Distribution email, portal or none)
Total issued by EFT to Supplier, issued amount and number of payments. Sorted by notice distribution Emial, Portal or None. 
Grand Totals for All EFT Client Non Mail and Supplier EFT',
            ),

            array(
                'id' => 108,
                'name' => 'CLIENT NON-MAIL SUPPLIER EFT NOTIFICATIONS BYPASSED (MI56J-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Report of EFT Notifications not sent to supplier with Bypass reason code (buld supplier, incomplete addres)',
            ),

            array(
                'id' => 109,
                'name' => 'MULTI-PAYMENT SUPPLIER EFT NOTIFICATION LISTING (MI56J-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Payments to Bulk Supplier where EFT notification sent by Email Address. Includes Payee (supplier) total amount, number of payments included, first and last payment number per supplier.',
            ),

            array(
                'id' => 110,
                'name' => 'Electronic Deposit Benefit Payments By Bank (MI13E-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'list of banks and showing Total deposits and total deposited amount by Bank. All deposits and total deposits for all banks at report summary',
            ),

            array(
                'id' => 111,
                'name' => 'EFT Transmittal Report (MI08D-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Control Total? Total EFT amount and Detail Count',
            ),

            array(
                'id' => 112,
                'name' => 'P030 Control Card Report (MI08C-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Control Card used by FASB to add manual broadcast message on SD0081 and EFT SD0081 IE T5007 tax slips mailed out by end of Feb 2025/',
            ),

            array(
                'id' => 113,
                'name' => 'F.I.C.S. Account Listing JPJ3 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly report. Comparison with JV report. Ask FASB for expanded description and report Requirements',
            ),

            array(
                'id' => 114,
                'name' => 'F.I.C.S. Account Listing EFT 02J3 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly report. EFT Comparison with JV report. Ask FASB for expanded Description and Report Requirements',
            ),

            array(
                'id' => 115,
                'name' => 'Control Totals for GL Journal Entries EFT 02J3 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly report. EFT Comparison with JV report. Ask FASB for expanded Description and Report Requirements',
            ),

            array(
                'id' => 116,
                'name' => 'Financial Transaction By Office - BC Benefits YW-EFT (MHR) 02J1 (MI210-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Reversed payments for the Month of XXX',
            ),

            array(
                'id' => 117,
                'name' => 'F.I.C.S. Account Listing EFT 02J1 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FISC Account list EFT to Supplier ?? Verify with FASB for expanded Description and Report Requirements',
            ),

            array(
                'id' => 118,
                'name' => 'Control Totals for GL Journal Entries JPJ3 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Reversed Computer Cheques BC Benefits/GAIN. Control totals for GL Journal entries. Selected month, up to run date.

',
            ),

            array(
                'id' => 119,
                'name' => 'Control Totals for GL Journal Entries EFT 02J1 (P210-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Reversed Computer cheques YW EFT
Control totals for GL Journal entries. Selected month, up to run date.
',
            ),

            array(
                'id' => 120,
                'name' => 'F.I.C.S. Account Listing JPJ1 (P220-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly report. Comparison with JV report. Supplier payments - Ask Fasb for extended description',
            ),

            array(
                'id' => 124,
                'name' => 'Exception Report - EFT (MI03S-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'EFT Exception report',
            ),

            array(
                'id' => 125,
                'name' => 'Cheque Reconciliation Reversals - EFT (MI04R-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Chequ Reconcilliation Reversal - Cheque number and referesal reason',
            ),

            array(
                'id' => 126,
                'name' => 'Exception Report - COMP (MI03S-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Exeption Report - payment cashed that were previoulsy written off',
            ),

            array(
                'id' => 127,
                'name' => 'Cheque Reconciliation Reversals - COMP (MI04R-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Cheque Reconciliation- Reversaio by Write Off or Cancelled list of payhment reversals - comp chqs. Listed by month. Totals for Cancelled reason a pre Pre-sent Values in MIS',
            ),

            array(
                'id' => 128,
                'name' => 'GAIN Office Table (P012-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'List of Ministry Offices',
            ),

            array(
                'id' => 129,
                'name' => 'BCCCU MONTHLY STATEMENT (AMP)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Central 1 summary Statement 
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic',
            ),

            array(
                'id' => 130,
                'name' => 'Cheque Reconciliation Monthly Cashed Mismatch (MI24N-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Chequ Reconcillition - Monthly Mismatched payments',
            ),

            array(
                'id' => 131,
                'name' => 'Monthly Report of All Lost or Stolen Cheques - (MI04F-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Cheques recorded as Lost or Stolen in the month',
            ),

            array(
                'id' => 132,
                'name' => 'Cheque Reconciliation - Outstanding List for Cheques Issued As of Month End (MI93T-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Outstanding- non cashed chques at end of month',
            ),

            array(
                'id' => 133,
                'name' => 'Cheque Reconciliation - Outstanding List for Cheques Issued Over 210 Days Ago (MI93S-02)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Outstanding payment issued over 210 days',
            ),

            array(
                'id' => 134,
                'name' => 'MIS Report of Uncleared Cheques (MI93S-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Report of Uncleared Cheque Payments',
            ),

            array(
                'id' => 135,
                'name' => 'Cheque Reconciliation - List of Imprest Cheques Issued (MI93R-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'TBD - to be reviewed',
            ),

            array(
                'id' => 136,
                'name' => 'Cheque Reconciliation Reversal Cross Reference Control Report (MI04O-99)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Control Totals Report',
            ),

            array(
                'id' => 137,
                'name' => 'CASES AUTOCLOSED DURING CHEQUE RUN',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Auto Closed during cheque run',
            ),

            array(
                'id' => 138,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits GAIN (MHR) JPJ7 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly Summary of GAIN Amount Issue- 
Product paid Product Code. Summary of Number of Computer payment to Client, supplier, trustee, and total amount of payments, total Records 
',
            ),

            array(
                'id' => 139,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits YW-EFT (MHR) 02J7 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Monthly Summary of YT EFT Amount Issue- 
Product paid Product Code. Summary of Number of Computer payment to Client, supplier, trustee, and total amount of payments, total Records',
            ),

            array(
                'id' => 140,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits YW-EFT (MHR) 02J3 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Total Reveresed payments by payment type (client/supplier etc)',
            ),

            array(
                'id' => 141,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits GAIN (MHR) JPJ3 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Total Reveresed payments by payment type (client/supplier etc)',
            ),

            array(
                'id' => 142,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits YW-EFT (MHR) 02J1 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB - Roll up of total $ per month by benefit Code',
            ),

            array(
                'id' => 143,
                'name' => 'Financial Transaction Provincial Summary - BC Benefits GAIN (MHR) JPJ1 (MI210-03)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'FASB - Roll up of total $ per month by benefit Clode',
            ),

            array(
                'id' => 144,
                'name' => 'BCCCU Account Activity And Balances (AMP)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Central 1 activity and balances
BCCCU reports come directly from Central 1 when the daily files are downloaded so we have no insight into the logic. There are similar reports for OD cheques that are also sent to R2W.
HMISJPJK carries out bank reconciliation processing and uses the DREC file to match cheque data from Central 1 to the MIS payment history DB2 tables and updates cashed cheque data.
Mismatched records are reported in report MI92T-01. which is also produced by job JPJK. 
A discovery session with FASB and possibly Treasury would be required to provide more details on the logic
',
            ),

            array(
                'id' => 145,
                'name' => 'OUTSTANDING WARRANTS MONITORING REPORT (MI95G-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case management Warrant
Active Contact on case has Outstanding Warrant box checked. Used by the field to assess for financial eligibilty
Report Conditions
Case Status: OPEN
Warant Flag on KP: YES and or Warrant Flag on Spouse Yes and Spouse End Date Null
Populate LAST NAME, FIRST NAME of contact with Warrant Flag Yes
',
            ),

            array(
                'id' => 146,
                'name' => 'FLEEING ABUSE CASE REPORT (MI56F-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management Alert - Type Fleeing Abuse
Used by the fields to manage Flee Abuse Cases, monitor for updating the Alert Flag and or Employment Obligations
Case Status Open
Review KP and Spo (end data null) 
If Alert Type =Fleeing Abuse and End date Null',
            ),

            array(
                'id' => 147,
                'name' => 'Hardship and Transition Monitoring By Worker (MI177-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - Hardship
Report Sorted by Office number, then Hardship Type. Used to monitor cases where Hardship has exceeded maximum months as per policy 

Report Requirments 
Case Status Open
Case Hardship Type Not Null, then include case in report, 
populate Legacy File ID, KP Name, Harship Type, Hardship End Date, Count number of months H/S benefits issued. Sort by Hardship Type 
',
            ),

            array(
                'id' => 148,
                'name' => 'GA Files with 3rd Unresolved Security Deposit (MI33A-01)',
                'created_at' => NULL,
                'updated_at' => NULL,
                'description' => 'Case Management - Security Deposit

Case Status OPEN
Debt Type: Security Deposit
Debt: has active balance >$0
Count number of active Sec Dep and if >3 include Case in report.',
            ),
        ));
    }
}
