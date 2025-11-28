<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CalculateFiveMinuteStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-stock:calculate-five-minute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily stock with five-minute granularity (wrapper for testing)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[' . now() . '] Starting five-minute stock calculation...');

        $exitCode = $this->call('daily-stock:calculate', [
            '--granularity' => 'five-minute'
        ]);

        $this->info('[' . now() . '] Five-minute stock calculation completed with exit code: ' . $exitCode);

        return $exitCode;
    }
}
