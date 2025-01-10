<?php

namespace Database\Seeders;

use App\Models\ErrorDataGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ErrorDataGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ErrorDataGroup::insert([['name' => 'Admission'],    ['name' => 'BusPassAttachments'],    ['name' => 'BusPassRefunds'],    ['name' => 'BusPassSticker'],    ['name' => 'BusPassTransLink'],    ['name' => 'Case Information'],    ['name' => 'CaseAddress'],    ['name' => 'CaseBenefitPlan'],    ['name' => 'CaseBenefitRecalc'],    ['name' => 'CaseClientBankInformation'],    ['name' => 'CaseContactEligibility'],    ['name' => 'CaseExpenses'],    ['name' => 'CaselAInformation'],    ['name' => 'CaseOrderRecalc'],    ['name' => 'CasePlan'],    ['name' => 'CasePositiveReport'],    ['name' => 'CaseServiceOrder'],    ['name' => 'CaseSignalCheque'],    ['name' => 'CFMS Case'],    ['name' => 'Contact Attachment'],    ['name' => 'ContactAboriginal'],    ['name' => 'ContactCaseAssets'],    ['name' => 'ContactCaselncome'],    ['name' => 'ContactCPA'],    ['name' => 'ContactImmigration'],    ['name' => 'ContactMerge'],    ['name' => 'ContactSanctionAdd'],    ['name' => 'ContactSanctionResolve'],    ['name' => 'ContactTombstone'],    ['name' => 'ExtendedHealthCoverage'],    ['name' => 'MSPCoverage'],    ['name' => 'RequestToCRA'],    ['name' => 'SeniorSupplementPayments'],]);
    }
}
