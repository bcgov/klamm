<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SendEmailReminders;

// Run Email Reminders Command Every Day at 7:00 AM
Schedule::command(SendEmailReminders::class)
    ->daily()
    ->weekdays()
    ->timezone('America/Vancouver')
    ->at('7:00')
    ->withoutOverlapping();
