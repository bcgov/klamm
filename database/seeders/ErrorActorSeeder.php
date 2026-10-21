<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ErrorActor;

class ErrorActorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actors = [
            'User',
            'System',
        ];

        foreach ($actors as $name) {
            ErrorActor::firstOrCreate(['name' => $name]);
        }
    }
}
