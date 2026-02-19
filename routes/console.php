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

Schedule::command('anonymization:purge-uploads --limit=200 --staging-limit=300 --staging-upload-chunk=50 --row-chunk=2000')
    ->everyThirtyMinutes()
    ->timezone('America/Vancouver')
    ->withoutOverlapping();
