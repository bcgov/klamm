<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MessageType;

class MessageTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table(table: 'message_types')->delete();

        $messageTypes = [
            'MIS Integration error',
            'ICM Data edit error',
            'ICM System message',
        ];

        foreach ($messageTypes as $messageType) {
            MessageType::firstOrCreate(['name' => $messageType]);
        }
    }
}
