<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler definitions (Laravel 11/12 style)
// Production: hourly; Testing: every five minutes
Schedule::command('sync:erp-data')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Enable this for testing more frequent runs
Schedule::command('sync:erp-data')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Run HR sync every hour (for production)
Schedule::command('sync:hr-data')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync.log'));

//Alternative: Run every 5 minutes for testing (uncomment to test)
// Schedule::command('sync:hr-data')
//     ->everyFiveMinutes()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/sync.log'));

// Refresh HR API token daily at 00:00
Schedule::command('hr:refresh-token')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/hr-api.log'));

// Check and ensure HR API token exists every 6 hours
// This will auto-login if token doesn't exist or is expired
Schedule::command('hr:ensure-token')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/hr-api.log'));

// Daily stock calculation with five-minute granularity
Schedule::command('daily-stock:calculate-five-minute')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-stock.log'))
    ->name('daily-stock-five-minute');
