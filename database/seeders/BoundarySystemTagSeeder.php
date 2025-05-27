<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BoundarySystemTag;

class BoundarySystemTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'Post-Month Processing',
            'MIS',
            'Payment Production',
            'Pre-Month Processing',
            'FDD- Case Management INT',
            'INT085',
            'Accounts Receivable',
            'INT081',
            'Misc MIS processes',
            'Debt',
            'Cheque Reconciliation',
            'INT683',
            'FDD- Contacts INT',
            'ICM INT',
            'CPP',
            'Month-End Processing',
            'AppGate',
            'INT684',
            'INT550',
            'FDD- Case Management  Appendix R INT',
            'CRA',
            'INT640',
            'INT641',
            'INT319',
            'FDD- Financials INT',
            'Bus Pass',
            'INT320',
            'INT312',
            'INT313',
            'INT307',
            'PGT',
            'INT615',
            'TDD- Case Management INT',
            'GenTax',
            'Sponsorship',
            'INT011',
            'FDD- Service Providers INT',
            'BCeID',
            'INT157',
            'INT338',
            'INT036',
            'CAS',
            'INT400',
            'INT401',
            'INT403',
            'INT477',
            'INT478',
            'INT006',
            'INT007',
            'INT010',
            'INT152',
            'INT305',
            'INT306',
            'INT409',
            'INT410',
            'INT601',
            'INT603',
            'INT606',
            'INT609',
            'INT612',
            'ODP',
            'INT310',
            'FDD- Attachments INT',
            'BC Mail Plus',
            'INT311',
            'INT416',
            'INT473',
            'INT501',
            'INT522',
            'INT552',
            'INT672',
            'INT675',
            'INT696',
            'INTM1.3TX03',
            'PWD',
            'INT407',
            'ICE',
            'IVR',
            'INT408',
            'INT414',
            'INT630',
            'INT611',
            'MPS',
            'INT301',
            'OAS',
            'GIS',
            'INT548',
            'T5007',
            'INT538',
            'HIBC',
            'MSP',
            'INT540',
            'INT546',
            'INT539',
            'INT547',
            'INT541',
            'PBC',
            'INT542',
            'INT543',
            'INT544',
            'INT554',
            'INT536',
            'EMPI'
        ];

        foreach ($tags as $tagName) {
            BoundarySystemTag::create([
                'name' => $tagName
            ]);
        }
    }
}
