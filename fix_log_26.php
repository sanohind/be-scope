<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SyncLog;

echo "Fixing log ID 26...\n";

$log = SyncLog::find(26);
if ($log && $log->status === 'running') {
    $log->status = 'failed';
    $log->completed_at = now();
    $log->error_message = 'Memory exhausted (before optimization)';
    $log->save();
    echo "✓ Log ID 26 updated to 'failed' status.\n";
} else {
    echo "Log ID 26 not found or already updated.\n";
}
