<?php

namespace Database\Seeders;

use App\Models\ErrorActor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
