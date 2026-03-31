<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErrorDataGroup;

class ErrorDataGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'Admission',
            'BusPassAttachments',
            'BusPassRefunds',
            'BusPassSticker',
            'BusPassTransLink',
            'Case Information',
            'CaseAddress',
            'CaseBenefitPlan',
            'CaseBenefitRecalc',
            'CaseClientBankInformation',
            'CaseContactEligibility',
            'CaseExpenses',
            'CaseIAInformation',
            'CaseOrderRecalc',
            'CasePlan',
            'CasePositiveReport',
            'CaseServiceOrder',
            'CaseSignalCheque',
            'CFMS Case',
            'Contact Attachment',
            'ContactAboriginal',
            'ContactCaseAssets',
            'ContactCaseIncome',
            'ContactCPA',
            'ContactImmigration',
            'ContactMerge',
            'ContactSanctionAdd',
            'ContactSanctionResolve',
            'ContactTombstone',
            'ExtendedHealthCoverage',
            'MSPCoverage',
            'RequestToCRA',
            'SeniorSupplementPayments',
        ];

        foreach ($groups as $name) {
            ErrorDataGroup::firstOrCreate(['name' => $name]);
        }
    }
}
