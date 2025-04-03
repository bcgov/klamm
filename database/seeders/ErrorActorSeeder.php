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
        ErrorActor::create(['name' => 'User']);
        ErrorActor::create(['name' => 'System']);
    }
}
