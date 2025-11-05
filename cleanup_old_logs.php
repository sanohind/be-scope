<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SyncLog;

echo "Cleaning up old 'running' sync logs...\n";

$updated = SyncLog::where('status', 'running')
    ->where('created_at', '<', now()->subHours(1))
    ->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_message' => 'Process interrupted or memory exhausted'
    ]);

echo "Updated {$updated} log(s) to 'failed' status.\n";

echo "\nCurrent sync logs:\n";
$logs = SyncLog::orderBy('id', 'desc')->take(10)->get();

foreach ($logs as $log) {
    echo sprintf(
        "ID: %d | Type: %s | Status: %s | Records: %d | Started: %s\n",
        $log->id,
        $log->sync_type,
        $log->status,
        $log->total_records,
        $log->started_at
    );
}

echo "\nCleanup completed!\n";
