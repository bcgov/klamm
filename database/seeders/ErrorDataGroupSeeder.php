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
        ErrorDataGroup::insert([
            ['name' => 'Admission'],
            ['name' => 'BusPassAttachments'],
            ['name' => 'BusPassRefunds'],
            ['name' => 'BusPassSticker'],
            ['name' => 'BusPassTransLink'],
            ['name' => 'Case Information'],
            ['name' => 'CaseAddress'],
            ['name' => 'CaseBenefitPlan'],
            ['name' => 'CaseBenefitRecalc'],
            ['name' => 'CaseClientBankInformation'],
            ['name' => 'CaseContactEligibility'],
            ['name' => 'CaseExpenses'],
            ['name' => 'CaseIAInformation'],
            ['name' => 'CaseOrderRecalc'],
            ['name' => 'CasePlan'],
            ['name' => 'CasePositiveReport'],
            ['name' => 'CaseServiceOrder'],
            ['name' => 'CaseSignalCheque'],
            ['name' => 'CFMS Case'],
            ['name' => 'Contact Attachment'],
            ['name' => 'ContactAboriginal'],
            ['name' => 'ContactCaseAssets'],
            ['name' => 'ContactCaseIncome'],
            ['name' => 'ContactCPA'],
            ['name' => 'ContactImmigration'],
            ['name' => 'ContactMerge'],
            ['name' => 'ContactSanctionAdd'],
            ['name' => 'ContactSanctionResolve'],
            ['name' => 'ContactTombstone'],
            ['name' => 'ExtendedHealthCoverage'],
            ['name' => 'MSPCoverage'],
            ['name' => 'RequestToCRA'],
            ['name' => 'SeniorSupplementPayments'],
        ]);
    }
}
