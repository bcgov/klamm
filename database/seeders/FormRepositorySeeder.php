<?php

namespace Database\Seeders;

use App\Models\FormRepository;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormRepositorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormRepository::create(['name' => 'ADO']);
        FormRepository::create(['name' => 'GitHub']);
        FormRepository::create(['name' => 'Klamm']);
        FormRepository::create(['name' => 'Adobe Workbench']);
    }
}
