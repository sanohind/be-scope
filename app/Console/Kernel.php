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
        // $schedule->command('sync:erp-data')
        //          ->hourly()
        //          ->withoutOverlapping()
        //          ->runInBackground()
        //          ->appendOutputTo(storage_path('logs/sync.log'));

        //Alternative: Run every 5 minutes for testing (uncomment to test)
        $schedule->command('sync:erp-data')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/sync.log'));
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
