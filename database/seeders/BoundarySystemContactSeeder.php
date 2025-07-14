<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BoundarySystemContact;
use App\Models\BoundarySystemContactEmail;

class BoundarySystemContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contacts = [
            [
                'name' => 'MIS Operators',
                'organization' => 'Government of BC',
                'emails' => ['OPERHELP@gov.bc.ca'],
                'notes' => 'MIS Operations team'
            ],
            [
                'name' => 'BC Mail Plus Generic',
                'organization' => 'BC Mail Plus',
                'emails' => ['Cruiser@gov.bc.ca'],
                'notes' => 'BC Mail Plus operations'
            ],
            [
                'name' => 'Provincial Treasury Help',
                'organization' => 'Provincial Treasury',
                'emails' => ['pthelp@gov.bc.ca'],
                'notes' => 'Provincial Treasury support'
            ],
            [
                'name' => 'CRA Data Group',
                'organization' => 'Canada Revenue Agency',
                'emails' => ['smtpecd@cra-arc.gc.ca', 'DataGroupPT@cra-arc.gc.ca'],
                'notes' => 'CRA data processing team'
            ],
            [
                'name' => 'RMS Support',
                'organization' => 'Revenue Management Services',
                'emails' => ['Andrew.Howson@gov.bc.ca'],
                'notes' => 'RMS system support'
            ],
            [
                'name' => 'ICM Application Support',
                'organization' => 'Government of BC',
                'emails' => ['SDSI.ICMApplicationSupport@gov.bc.ca', 'ICM.WebMethods.PROD@gov.bc.ca'],
                'notes' => 'ICM application support team'
            ],
            [
                'name' => 'Service Canada',
                'organization' => 'Service Canada',
                'emails' => ['NC-ORD-BDRO-GD@servicecanada.gc.ca', 'FTPEXCHANGE@hrsdc-rhdcc.gc.ca'],
                'notes' => 'Service Canada operations'
            ],
            [
                'name' => 'CAS Help',
                'organization' => 'Corporate Accounting Services',
                'emails' => ['CASHELP@gov.bc.ca'],
                'notes' => 'CAS system support'
            ],
            [
                'name' => 'BCeID Service Desk',
                'organization' => 'Government of BC',
                'emails' => ['bceidtier3@gov.bc.ca', 'idim.consulting@gov.bc.ca'],
                'notes' => 'BCeID identity management'
            ],
            [
                'name' => 'Translink Bus Pass',
                'organization' => 'Translink',
                'emails' => ['servicedesk@translink.ca'],
                'notes' => 'Translink bus pass operations'
            ],
            [
                'name' => 'Public Guardian and Trustee',
                'organization' => 'PGT',
                'emails' => ['gsidhu@trustee.bc.ca', 'lbrown@trustee.bc.ca', 'jberry@trustee.bc.ca'],
                'notes' => 'Senior Supplement Payment Files'
            ],
            [
                'name' => 'Computer Talk IVR',
                'organization' => 'Computer Talk',
                'emails' => ['NMartel@computer-talk.com'],
                'notes' => 'IVR system support'
            ],
            [
                'name' => 'HIBC MSP Enrollment',
                'organization' => 'Health Insurance BC',
                'emails' => ['marri.todd@hibc.gov.bc.ca', 'oleg.matveenko@pbcsolutions.ca'],
                'notes' => 'MSP enrollment support'
            ],
            [
                'name' => 'Pacific Blue Cross',
                'organization' => 'Pacific Blue Cross',
                'emails' => ['ADizdarevic@pac.bluecross.ca', 'SJung@pac.bluecross.ca'],
                'notes' => 'Extended health enrollment'
            ],
            [
                'name' => 'EMPI Support',
                'organization' => 'CGI',
                'emails' => ['Ams-registries.vic@cgi.com'],
                'notes' => 'PHN verification system'
            ],
            [
                'name' => 'CRA Tax Information',
                'organization' => 'Canada Revenue Agency',
                'emails' => ['angela.lacoste@cra-arc.gc.ca', 'Brian.Mweu@cra-arc.gc.ca'],
                'notes' => 'Tax information services'
            ],
            [
                'name' => 'Central 1 Credit Union',
                'organization' => 'Central 1 Credit Union',
                'emails' => [],
                'notes' => 'Banking services'
            ],
            [
                'name' => 'Canada Post',
                'organization' => 'Canada Post',
                'emails' => [],
                'notes' => 'Postal services'
            ],
            [
                'name' => 'RBC Bus Pass Payments',
                'organization' => 'Royal Bank of Canada',
                'emails' => ['Jason.L.Bevins@gov.bc.ca'],
                'notes' => 'Bus Pass payment processing'
            ],
            [
                'name' => 'HPAS PPC',
                'organization' => 'HP Advanced Solutions',
                'emails' => [],
                'notes' => 'Payment Processing Center'
            ]
        ];

        foreach ($contacts as $contactData) {
            $contact = BoundarySystemContact::create([
                'name' => $contactData['name'],
                'organization' => $contactData['organization'],
                'notes' => $contactData['notes']
            ]);

            foreach ($contactData['emails'] as $email) {
                if (!empty($email)) {
                    BoundarySystemContactEmail::create([
                        'contact_id' => $contact->id,
                        'email' => $email
                    ]);
                }
            }
        }
    }
}
