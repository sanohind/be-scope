<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // // Run ERP sync every hour (for production)
        $schedule->command('sync:erp-data')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/sync.log'));

        //Alternative: Run every 5 minutes for testing (uncomment to test)
        // $schedule->command('sync:erp-data')
        //          ->everyFiveMinutes()
        //          ->withoutOverlapping()
        //          ->runInBackground()
        //          ->appendOutputTo(storage_path('logs/sync.log'));

        // Run HR sync every hour (for production)
        $schedule->command('sync:hr-data')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/sync.log'));

        //Alternative: Run every 5 minutes for testing (uncomment to test)
        // $schedule->command('sync:hr-data')
        //          ->everyFiveMinutes()
        //          ->withoutOverlapping()
        //          ->runInBackground()
        //          ->appendOutputTo(storage_path('logs/sync.log'));

        // Refresh HR API token daily at 00:00
        $schedule->command('hr:refresh-token')
                 ->daily()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/hr-api.log'));

        // Check and ensure HR API token exists every 6 hours
        // This will auto-login if token doesn't exist or is expired
        $schedule->command('hr:ensure-token')
                 ->everySixHours()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/hr-api.log'));

        // Daily stock calculation
        // $env = $this->app->environment();

        $schedule->command('daily-stock:calculate')
                     ->dailyAt('06:00')
                     ->withoutOverlapping()
                     ->appendOutputTo(storage_path('logs/daily-stock.log'))
                     ->name('daily-stock-production');

        $schedule->command('stock:snapshot')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/stock-snapshot.log'))
                 ->name('stock-by-wh-snapshot');

        // if ($env === 'production') {
        //     // Production: daily at 06:00 with daily granularity (default)
        //     $schedule->command('daily-stock:calculate')
        //              ->dailyAt('06:00')
        //              ->withoutOverlapping()
        //              ->appendOutputTo(storage_path('logs/daily-stock.log'))
        //              ->name('daily-stock-production');
        // } else {
        //     // Testing: every 5 minutes with five-minute granularity (non-production)
        //     $schedule->command('daily-stock:calculate-five-minute')
        //              ->everyFiveMinutes()
        //              ->withoutOverlapping()
        //              ->appendOutputTo(storage_path('logs/daily-stock.log'))
        //              ->name('daily-stock-five-minute');
        // }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
