<?php

namespace Database\Seeders;

use App\Models\FormMetadata\FormRepository;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormRepositorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $repositories = [
            'ADO',
            'GitHub',
            'Klamm',
            'Adobe Workbench',
        ];

        foreach ($repositories as $name) {
            FormRepository::firstOrCreate(['name' => $name]);
        }
    }
}
