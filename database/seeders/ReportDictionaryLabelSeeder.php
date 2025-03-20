<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ReportDictionaryLabel;

class ReportDictionaryLabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reportDctionaryLabels = [
            'SIN',
            'ICM PID',
            'MIS PID',
            'Legacy ID',
            'Case Name',
            'Category',
            'O/S Warrant',
            'Alert Text',
            'Hardship Code',
            'Hardship Start Date',
            'Cheque Production',
            'Case Office',
            'Phone',
            'Class',
            'First Name',
            'Last Name',
            'Alert',
            'Start Date',
            'Spouse First Name',
            'Spouse Last Name',
            'H/S Start Date',
            'Payee',
            'Address',
            'Postal Code',
            'Obsolete',
            'PHN',
            'MSO Reason',
            'N/A',
            'MSO Review Date',
            'Distribution Method',
            'Sponsorship Start Date',
            'Sponsorship Expiry Date',
            'Date of Birth',
            'Open Date',
            'Case Status',
            'Office',
            'Gender',
            'Closed Date',
            'Relationship',
            'Owner Office',
            'Ministry Office',
            'Office Name',
            'Street Address',
            'City',
            'Office Type',
            'Closed Code',
            'Close Reason',
            'Cheque Date',
            'Additional Reason',
            'Cheque amount',
            'Manual Admin amount',
            'Payment Method',
            'Cheque Number',
            'Distribution Code',
        ];

        foreach ($reportDctionaryLabels as $reportDictionaryLabel) {
            ReportDictionaryLabel::create(['name' => $reportDictionaryLabel]);
        }
    }
}
