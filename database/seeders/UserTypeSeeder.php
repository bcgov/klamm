<?php

namespace Database\Seeders;

use App\Models\FormMetadata\UserType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userTypes = [
            'Internal',
            'Public',
            'Public with Disabilities',
        ];

        foreach ($userTypes as $name) {
            UserType::firstOrCreate(['name' => $name]);
        }
    }
}
