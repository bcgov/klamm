<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BoundarySystem;
use App\Models\BoundarySystemContact;

class BoundarySystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $systems = [
            [
                'name' => 'MIS',
                'description' => 'Ministry Information System',
                'is_external' => false,
                'contact_name' => 'MIS Operators'
            ],
            [
                'name' => 'CAS',
                'description' => 'Corporate Accounting Services',
                'is_external' => false,
                'contact_name' => 'CAS Help'
            ],
            [
                'name' => 'BC Mail Plus',
                'description' => 'BC Mail Plus printing and mailing services',
                'is_external' => true,
                'contact_name' => 'BC Mail Plus Generic'
            ],
            [
                'name' => 'Provincial Treasury',
                'description' => 'Provincial Treasury banking and financial services',
                'is_external' => false,
                'contact_name' => 'Provincial Treasury Help'
            ],
            [
                'name' => 'CRA',
                'description' => 'Canada Revenue Agency',
                'is_external' => true,
                'contact_name' => 'CRA Data Group'
            ],
            [
                'name' => 'Canada Post',
                'description' => 'Canada Post postal code services',
                'is_external' => true,
                'contact_name' => 'Canada Post'
            ],
            [
                'name' => 'RMS',
                'description' => 'Revenue Management Services',
                'is_external' => false,
                'contact_name' => 'RMS Support'
            ],
            [
                'name' => 'Central 1 Credit Union',
                'description' => 'Central 1 Credit Union banking services',
                'is_external' => true,
                'contact_name' => 'Central 1 Credit Union'
            ],
            [
                'name' => 'ICM',
                'description' => 'Integrated Case Management',
                'is_external' => false,
                'contact_name' => 'ICM Application Support'
            ],
            [
                'name' => 'Service Canada',
                'description' => 'Service Canada federal services',
                'is_external' => true,
                'contact_name' => 'Service Canada'
            ],
            [
                'name' => 'Translink',
                'description' => 'Translink transit services',
                'is_external' => true,
                'contact_name' => 'Translink Bus Pass'
            ],
            [
                'name' => 'HPAS PPC',
                'description' => 'HP Advanced Solutions Payment Processing Center',
                'is_external' => true,
                'contact_name' => 'HPAS PPC'
            ],
            [
                'name' => 'RBC',
                'description' => 'Royal Bank of Canada',
                'is_external' => true,
                'contact_name' => 'RBC Bus Pass Payments'
            ],
            [
                'name' => 'PGT',
                'description' => 'Public Guardian and Trustee of British Columbia',
                'is_external' => false,
                'contact_name' => 'Public Guardian and Trustee'
            ],
            [
                'name' => 'GenTax',
                'description' => 'GenTax platform for Taxpayer Administration, Compliance & Services',
                'is_external' => false,
                'contact_name' => null
            ],
            [
                'name' => 'BCeID',
                'description' => 'BC Electronic Identity system',
                'is_external' => false,
                'contact_name' => 'BCeID Service Desk'
            ],
            [
                'name' => 'CAS DW',
                'description' => 'Corporate Accounting Services Data Warehouse',
                'is_external' => false,
                'contact_name' => 'CAS Help'
            ],
            [
                'name' => 'IVR/ICE',
                'description' => 'Interactive Voice Response / Interactive Customer Experience',
                'is_external' => true,
                'contact_name' => 'Computer Talk IVR'
            ],
            [
                'name' => 'MPS Print Servers',
                'description' => 'Managed Print Services print servers',
                'is_external' => false,
                'contact_name' => null
            ],
            [
                'name' => 'Client Portal',
                'description' => 'MySS client portal',
                'is_external' => false,
                'contact_name' => null
            ],
            [
                'name' => 'HIBC',
                'description' => 'Health Insurance BC',
                'is_external' => false,
                'contact_name' => 'HIBC MSP Enrollment'
            ],
            [
                'name' => 'PBC',
                'description' => 'Pacific Blue Cross',
                'is_external' => true,
                'contact_name' => 'Pacific Blue Cross'
            ],
            [
                'name' => 'EMPI',
                'description' => 'Enterprise Master Patient Index',
                'is_external' => false,
                'contact_name' => 'EMPI Support'
            ]
        ];

        foreach ($systems as $systemData) {
            $contact = null;
            if ($systemData['contact_name']) {
                $contact = BoundarySystemContact::where('name', $systemData['contact_name'])->first();
            }

            BoundarySystem::create([
                'name' => $systemData['name'],
                'description' => $systemData['description'],
                'is_external' => $systemData['is_external'],
                'contact_id' => $contact ? $contact->id : null
            ]);
        }
    }
}
