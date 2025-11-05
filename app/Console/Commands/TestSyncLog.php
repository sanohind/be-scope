<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncLog;

class TestSyncLog extends Command
{
    protected $signature = 'test:sync-log';
    protected $description = 'Test if SyncLog is working properly';

    public function handle()
    {
        $this->info('Testing SyncLog model...');
        
        // Create a test log
        $this->info('Creating test sync log...');
        $testLog = SyncLog::create([
            'sync_type' => 'test',
            'status' => 'running',
            'started_at' => now(),
            'total_records' => 0,
            'success_records' => 0,
            'failed_records' => 0,
        ]);
        
        $this->info("✓ Created sync log with ID: {$testLog->id}");
        
        // Update the log
        $this->info('Updating sync log...');
        $testLog->status = 'completed';
        $testLog->completed_at = now();
        $testLog->total_records = 100;
        $testLog->success_records = 100;
        $testLog->save();
        
        $this->info("✓ Updated sync log ID: {$testLog->id}");
        
        // Read back the log
        $this->info('Reading sync log from database...');
        $readLog = SyncLog::find($testLog->id);
        
        if ($readLog) {
            $this->info("✓ Found sync log:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $readLog->id],
                    ['Sync Type', $readLog->sync_type],
                    ['Status', $readLog->status],
                    ['Started At', $readLog->started_at],
                    ['Completed At', $readLog->completed_at],
                    ['Total Records', $readLog->total_records],
                    ['Success Records', $readLog->success_records],
                    ['Failed Records', $readLog->failed_records],
                    ['Created At', $readLog->created_at],
                    ['Updated At', $readLog->updated_at],
                ]
            );
        } else {
            $this->error("✗ Could not find sync log with ID: {$testLog->id}");
            return 1;
        }
        
        // List all sync logs
        $this->info('All sync logs:');
        $allLogs = SyncLog::orderBy('id', 'desc')->take(5)->get();
        
        if ($allLogs->count() > 0) {
            $this->table(
                ['ID', 'Type', 'Status', 'Total Records', 'Started At', 'Completed At'],
                $allLogs->map(function($log) {
                    return [
                        $log->id,
                        $log->sync_type,
                        $log->status,
                        $log->total_records,
                        $log->started_at,
                        $log->completed_at,
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No sync logs found in database.');
        }
        
        // Delete test log
        $this->info('Deleting test sync log...');
        $testLog->delete();
        $this->info("✓ Deleted test sync log");
        
        $this->info('');
        $this->info('✓ SyncLog test completed successfully!');
        
        return 0;
    }
}
