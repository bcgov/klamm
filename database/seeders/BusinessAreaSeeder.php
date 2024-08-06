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
        $businessAreas = [
            ['short_name' => 'SDPR', 'name' => 'Bus Pass'],
            ['short_name' => 'MCFD', 'name' => 'MCFD Service Delivery Division (SDD MCFD)'],
            ['short_name' => 'MCFD', 'name' => 'Finance & Corporate Services'],
            ['short_name' => 'MCFD', 'name' => 'Service Delivery Division (SDD MCFD)'],
            ['short_name' => 'SDPR', 'name' => 'Youth Justice'],
            ['short_name' => 'SDPR', 'name' => 'Child Protection and Family Services'],
            ['short_name' => null, 'name' => 'Adoptions & Permanency'],
            ['short_name' => 'SDPR', 'name' => 'Change Advisory Board'],
            ['short_name' => 'SDPR', 'name' => 'Contract Management Branch'],
            ['short_name' => 'SDPR', 'name' => 'Child Welfare'],
            ['short_name' => 'MCFD', 'name' => 'Information, Privacy and Records Services Branch'],
            ['short_name' => null, 'name' => 'Affordable Child Care Benefit'],
            ['short_name' => 'SDPR', 'name' => 'Child Youth Mental Health (CYMH)'],
            ['short_name' => 'SDPR', 'name' => 'Children Youth Support Needs (CYSN)'],
            ['short_name' => 'SDPR', 'name' => 'Youth Services'],
            ['short_name' => null, 'name' => 'Autism Funding'],
            ['short_name' => 'MCFD', 'name' => 'Interim Authority Community Living'],
            ['short_name' => 'SDPR', 'name' => 'Early Years'],
            ['short_name' => 'SDPR', 'name' => 'Child Care Capital Funding'],
            ['short_name' => 'SDPR', 'name' => 'Child Care Operating Funding'],
            ['short_name' => 'SDPR', 'name' => 'Childcare BC New Spaces Fund'],
            ['short_name' => 'SDPR', 'name' => 'Early Childhood Educator Registry (ECER)'],
            ['short_name' => 'SDPR', 'name' => 'Child Protection and Family Services; Guardianship'],
            ['short_name' => null, 'name' => 'Adoptions & Permanency; Guardianship'],
            ['short_name' => 'SDPR', 'name' => 'Youth Advisory Council (YAC)'],
            ['short_name' => 'MCFD', 'name' => 'Resources'],
            ['short_name' => null, 'name' => 'Adoptions & Permanency; Child Welfare'],
            ['short_name' => 'MCFD', 'name' => 'Provincial Services'],
            ['short_name' => 'MCFD', 'name' => 'Personnel'],
            ['short_name' => 'SDPR', 'name' => 'Youth Forensic Psychiatric Services'],
            ['short_name' => null, 'name' => 'At Home Program'],
            ['short_name' => 'SDPR', 'name' => 'Children Youth Support Needs (CYSN); Medical Benefits'],
            ['short_name' => 'MCFD', 'name' => 'Medical Benefits'],
            ['short_name' => 'MCFD', 'name' => 'Procurement and Contract Services'],
            ['short_name' => 'SDPR', 'name' => 'Child Protection and Family Services; CYSN'],
            ['short_name' => 'SDPR', 'name' => 'Child Protection and Family Services; Youth Services'],
            ['short_name' => 'SDPR', 'name' => 'Child Protection and Family Services; Guardianship; Adoptions & Permanency'],
            ['short_name' => 'MCFD', 'name' => 'Guardianship'],
            ['short_name' => null, 'name' => 'Affordable Child Care Benefit, Child Care Operating Funding'],
            ['short_name' => 'SDPR', 'name' => 'Verification and Audit Unit'],
            ['short_name' => null, 'name' => 'Autism Funding; Medical Benefits'],
            ['short_name' => 'MCFD', 'name' => 'Legal Support'],
            ['short_name' => 'MCFD', 'name' => 'Poverty Initiative'],
            ['short_name' => 'MCFD', 'name' => 'Provincial Deaf Hard of Hearing Services (PDHHS)'],
            ['short_name' => null, 'name' => 'Applied Practice Research and Learning'],
            ['short_name' => 'SDPR', 'name' => 'Centralized Services Hub'],
            ['short_name' => 'SDPR', 'name' => 'Services to Adults With Developmental Disabilities Program (STADD)'],
            ['short_name' => 'SDPR', 'name' => 'Specialized Homes Support Services (SHSS)'],
            ['short_name' => 'MCFD', 'name' => 'MCFD Practice Team'],
            ['short_name' => 'MCFD', 'name' => 'Indigenous Child and Family Services Agency (ICFSA)'],
            ['short_name' => 'SDPR', 'name' => 'Strengthening Abilities Journeys of Empowerment (SAJE)'],
            ['short_name' => 'SDPR', 'name' => 'Youth Custody Services'],
            ['short_name' => 'SDPR', 'name' => 'Service Delivery Division (SDD SDPR)'],
            ['short_name' => 'MCFD', 'name' => 'SDPR Intake'],
            ['short_name' => 'MCFD', 'name' => 'Prevention and Loss Management Services (PLMS)'],
            ['short_name' => 'MCFD', 'name' => 'Financial Administrative Services Branch (FASB)'],
            ['short_name' => 'MCFD', 'name' => 'SDPR Service Delivery Division (SDD SDPR)'],
            ['short_name' => 'SDPR', 'name' => 'Employment Labour Market Services Division (ELMSD)'],
            ['short_name' => 'MCFD', 'name' => 'Research, Innovation and Policy Division'],
            ['short_name' => 'MCFD', 'name' => 'SDPR Corporate Planning and Strategic Initiatives'],
            ['short_name' => 'SDPR', 'name' => 'Employment and Assistance Appeals Tribunal'],
            ['short_name' => 'SDPR', 'name' => 'Corporate Services Division'],
            ['short_name' => 'MCFD', 'name' => 'FMEP'],
            ['short_name' => 'SDPR', 'name' => 'CPP Recovery Program'],
            ['short_name' => 'MCFD', 'name' => 'Information Services Division'],
            ['short_name' => 'SDPR', 'name' => 'Corporate Communications'],
            ['short_name' => 'SDPR', 'name' => 'Service Delivery Division Operations Support'],
            ['short_name' => 'SDPR', 'name' => 'Employee Initiated Transfer Request (EITR)'],
            ['short_name' => 'SDPR', 'name' => 'Corporate Planning and Strategic Initiatives'],
            ['short_name' => null, 'name' => 'Analytics and Business Intelligence'],
        ];

        foreach ($businessAreas as $area) {
            $businessArea = new BusinessArea();
            $businessArea->name = $area['name'];
            $businessArea->save();

            if ($area['short_name']) {
                $ministry = Ministry::where('short_name', $area['short_name'])->first();
                if ($ministry) {
                    $businessArea->ministries()->attach($ministry->id);
                }
            }
        }
    }
}
