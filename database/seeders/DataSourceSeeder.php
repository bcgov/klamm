<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * $table->string('name', 400);
     * $table->text('description')->nullable();
     * $table->text('documentation')->nullable();
     */
    public function run(): void
    {
        $icm = new DataSource();
        $icm->name = 'ICM';
        $icm->description = 'The Integrated Case Management solution for SDPR and MCFD, built on Siebel CRM';
        $icm->save();

        $oes = new DataSource();
        $oes->name = 'OES';
        $oes->description = 'Online Employment System used by ELMSD';
        $oes->save();
    }
}
