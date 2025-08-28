<?php

namespace Database\Seeders;

use App\Models\Ministry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MinistrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * $table->id();
     * $table->string('short_name', 20);
     * $table->string('name', 400);
     */
    public function run(): void
    {
        $mcfd = new Ministry();
        $mcfd->short_name = 'MCFD';
        $mcfd->name = 'Ministry of Children and Family Development';
        $mcfd->save();

        $sdpr = new Ministry();
        $sdpr->short_name = 'SDPR';
        $sdpr->name = 'Ministry of Social Development and Poverty Reduction';
        $sdpr->save();

        $ecc = new Ministry();
        $ecc->short_name = 'ECC';
        $ecc->name = 'Ministry of Education and Child Care';
        $ecc->save();

        $fedgovbc = new Ministry();
        $fedgovbc->short_name = 'FEDGOVBC';
        $fedgovbc->name = 'Government of Canada and Province of British Columbia';
        $fedgovbc->save();
    }
}
