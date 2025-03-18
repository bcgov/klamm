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
        $reports = [
            'AoB Unmatch Report Details (MI59N-01)',
            'AoB Match Report Details (MI59M-02)',
            'OUTSTANDING WARRANTS MONITORING REPORT (MI95G-01)',
            'GA Files with 3rd Unresolved Security Deposit (MI33A-01)',
            'FLEEING ABUSE CASE REPORT (MI56F-01)',
            'Hardship and Transition Monitoring By Worker (MI177-01)',
            'Computer Cheques and EFTs Issued (MI116-01)',
            'MSO MONITORING REPORT (MI95W-01)',
            'BCEA Computer Cheques Requiring Review (p071-01)',
            'Gain-Family Bonus (FB) Discrepancies (MI08U-01)',
            'Financial Worker Field Advice (MI137-01)',
            'Financial Worker Review Report (MI137-02)',
            'CPP Income Load - Case Review List (MI59H-01)',
            'EI Common Claimant Report (CCR) (MI027-02)',
            'EI Common Claimant Report (CCR) (MI027-01)',
            'Inter-Prov File Match Caseload Summary - BC & MB (IPR) - (MI561-02)',
            'Inter-Prov File Match Caseload Summary - BC & MB (DIST) - (MI561-01)',
            'Inter-Prov File Match Caseload Summary - BC & ON (DIST) - (MI561-02)',
            'Inter-Prov File Match Caseload Summary - BC & ON (DIST) - (MI561-01)',
            'Inter-Prov File Match Caseload Summary - BC & SK (IPR) - (MI561-02)',
            'Inter-Prov File Match Caseload Summary - BC & SK (DIST) - (MI561-01)',
            'Inter-Prov File Match Caseload Summary - BC & AB (IPR) - (MI561-02)',
            'Inter-Prov File Match Caseload Summary - BC & AB (DIST) - (MI561-01)',
            'Open GAIN Clients Missing SINs Report (MI579-02)',
            'UnsubmittedChartReport (OPC001-01)',
            'AR Collections - Data Rejected for Transfer to Collection (MI94K-02)',
            'AR Collections - Data Sent for Collection (MI94K-01)',
            'Daily Unallocated Payments - MI766-02',
            'Overpayment Monitoring Report (MI54K-01)',
            'RMS To Gain Repayment Upload Error Report (MI10B-01)',
            'MIS Debt Select-Extract Outstanding Balances By Type (MI26K-01)',
            'LIST OF REPAYMENT ADJUSTMENT (MI07J-02)',
            'Cheque Reconciliation Daily Cash Mismatch (MI92T-01)',
            'BCEA COMPUTER PAYMENTS OVER (MI98C-01)',
            'Cheque Reconciliation Outstanding List (MI04E-01)',
            'CLIENT NON-MAIL SUPPLIER EFT NOTIFICATIONS BYPASSED (MI56J-03)',
            'Cheque Reconciliation Reversals - ODCHQ (MI04R-01)',
            'Cheque Reconciliation Reversals - EFT (MI04R-01)',
            'Cheque Reconciliation Reversals - COMP (MI04R-01)',
            'Cheque Reconciliation Monthly Cashed Mismatch (MI24N-02)',
            'Monthly Report of All Lost or Stolen Cheques - (MI04F-01)',
            'Cheque Reconciliation - Outstanding List for Cheques Issued As of Month End (MI93T-01)',
            'Cheque Reconciliation - Outstanding List for Cheques Issued Over 210 Days Ago (MI93S-02)',
            'CASES AUTOCLOSED DURING CHEQUE RUN',
            'BCEA DEBT REPORT (MI361-01)',
            'Daily Cancelled Cheque Activity - (MI766-01)',
            'GAIN Office Table (P012-01)'
        ];

        foreach ($reports as $report) {
            Report::create(['name' => $report]);
        }
    }
}
