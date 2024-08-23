<?php

namespace Database\Seeders;

use App\Models\BusinessArea;
use App\Models\Ministry;
use Illuminate\Database\Seeder;

class BusinessAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        BusinessArea::truncate();

        $businessAreas = [
            ['short_name' => null, 'name' => 'Bus Pass'],
            ['short_name' => 'CYSN', 'name' => 'Children Youth Support Needs (CYSN)'],
            ['short_name' => null, 'name' => 'Child Protection and Family Services'],
            ['short_name' => null, 'name' => 'Information Privacy and Records Services Branch'],
            ['short_name' => null, 'name' => 'Quality Assurance'],
            ['short_name' => null, 'name' => 'Child Welfare'],
            ['short_name' => null, 'name' => 'Affordable Child Care Benefit'],
            ['short_name' => 'CYMH', 'name' => 'Child Youth Mental Health (CYMH)'],
            ['short_name' => null, 'name' => 'Youth Services'],
            ['short_name' => 'SAJE', 'name' => 'Strengthening Abilities Journeys of Empowerment (SAJE)'],
            ['short_name' => null, 'name' => 'Autism Funding'],
            ['short_name' => null, 'name' => 'Provincial Autism Initiatives Branch'],
            ['short_name' => null, 'name' => 'Child Care Subsidy'],
            ['short_name' => 'SDD MCFD', 'name' => 'Service Delivery Division (SDD MCFD)'],
            ['short_name' => null, 'name' => 'Courts and Legislation'],
            ['short_name' => null, 'name' => 'Medical Benefits'],
            ['short_name' => 'SDD SDPR', 'name' => 'Service Delivery Division (SDD SDPR)'],
            ['short_name' => 'PLMS', 'name' => 'Prevention and Loss Management Services (PLMS)'],
            ['short_name' => 'ELMSD', 'name' => 'Employment Labour Market Services Division (ELMSD)'],
            ['short_name' => null, 'name' => 'Information Services Division'],
            ['short_name' => null, 'name' => 'SDPR Corporate Planning and Strategic Initiatives'],
            ['short_name' => null, 'name' => 'Finance & Corporate Services'],
            ['short_name' => 'SDD MCFD', 'name' => 'MCFD Service Delivery Division (SDD MCFD)'],
            ['short_name' => null, 'name' => 'Records and Forms Operations'],
            ['short_name' => null, 'name' => 'Aboriginal Support Services'],
            ['short_name' => null, 'name' => 'Personnel'],
            ['short_name' => null, 'name' => 'Adoptions & Permanency'],
            ['short_name' => null, 'name' => 'Youth Justice'],
            ['short_name' => null, 'name' => 'Youth Custody Services'],
            ['short_name' => null, 'name' => 'Youth Community Justice'],
            ['short_name' => null, 'name' => 'Change Advisory Board'],
            ['short_name' => null, 'name' => 'Capital and Asset Management'],
            ['short_name' => null, 'name' => 'Procurement and Contract Services'],
            ['short_name' => null, 'name' => 'Contract Management Branch'],
            ['short_name' => null, 'name' => 'Child Care Operating Funding'],
            ['short_name' => null, 'name' => 'Occupational Health and Safety'],
            ['short_name' => null, 'name' => 'Safe Care Team'],
            ['short_name' => null, 'name' => 'Interim Authority Community Living'],
            ['short_name' => null, 'name' => 'Guardianship'],
            ['short_name' => null, 'name' => 'Early Years'],
            ['short_name' => null, 'name' => 'Child Care Capital Funding'],
            ['short_name' => null, 'name' => 'Childcare BC New Spaces Fund'],
            ['short_name' => 'ECER', 'name' => 'Early Childhood Educator Registry (ECER)'],
            ['short_name' => 'YAC', 'name' => 'Youth Advisory Council (YAC)'],
            ['short_name' => null, 'name' => 'Resources'],
            ['short_name' => null, 'name' => 'Provincial Services'],
            ['short_name' => null, 'name' => 'Youth Forensic Psychiatric Services'],
            ['short_name' => null, 'name' => 'At Home Program'],
            ['short_name' => null, 'name' => 'Nursing Support Services'],
            ['short_name' => null, 'name' => 'Verification and Audit Unit'],
            ['short_name' => null, 'name' => 'Legal Support'],
            ['short_name' => null, 'name' => 'Poverty Initiative'],
            ['short_name' => 'PDHHS', 'name' => 'Provincial Deaf Hard of Hearing Services (PDHHS)'],
            ['short_name' => null, 'name' => 'Applied Practice Research and Learning'],
            ['short_name' => null, 'name' => 'Centralized Services Hub'],
            ['short_name' => 'STADD', 'name' => 'Services to Adults With Developmental Disabilities Program (STADD)'],
            ['short_name' => null, 'name' => 'Assets and Facility Management Branch'],
            ['short_name' => null, 'name' => 'Major Capital'],
            ['short_name' => null, 'name' => 'Accounts Payable'],
            ['short_name' => 'SHSS', 'name' => 'Specialized Homes Support Services (SHSS)'],
            ['short_name' => null, 'name' => 'Practice Team'],
            ['short_name' => 'ICFSA', 'name' => 'Indigenous Child and Family Services Agency (ICFSA)'],
            ['short_name' => null, 'name' => 'SDPR Intake'],
            ['short_name' => 'FASB', 'name' => 'Financial Administrative Services Branch (FASB)'],
            ['short_name' => 'SDD SDPR', 'name' => 'SDPR Service Delivery Division (SDD SDPR)'],
            ['short_name' => null, 'name' => 'FMEP'],
            ['short_name' => null, 'name' => 'Research Innovation and Policy Division'],
            ['short_name' => null, 'name' => 'Corporate Planning and Strategic Initiatives'],
            ['short_name' => null, 'name' => 'CPP Recovery Program'],
            ['short_name' => null, 'name' => 'Employment and Assistance Appeals Tribunal'],
            ['short_name' => null, 'name' => 'Corporate Services Division'],
            ['short_name' => null, 'name' => 'Corporate Communications'],
            ['short_name' => null, 'name' => 'Service Delivery Division Operations Support'],
            ['short_name' => 'EITR', 'name' => 'Employee Initiated Transfer Request (EITR)'],
            ['short_name' => null, 'name' => 'Analytics and Business Intelligence'],
        ];

        foreach ($businessAreas as $area) {
            BusinessArea::create($area);
        }
    }
}
