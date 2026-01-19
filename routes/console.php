<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SendEmailReminders;
use App\Console\Commands\PurgeAnonymizationUploads;

// Run Email Reminders Command Every Day at 7:00 AM
Schedule::command(SendEmailReminders::class)
    ->daily()
    ->weekdays()
    ->timezone('America/Vancouver')
    ->at('7:00')
    ->withoutOverlapping();

Schedule::command(PurgeAnonymizationUploads::class)
    ->daily()
    ->timezone('America/Vancouver')
    ->at('2:15')
    ->withoutOverlapping();
