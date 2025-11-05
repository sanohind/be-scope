<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';
    
    public $timestamps = true;
    
    protected $fillable = [
        'sync_type',
        'status',
        'started_at',
        'completed_at',
        'total_records',
        'success_records',
        'failed_records',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getDurationAttribute()
    {
        if ($this->completed_at && $this->started_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    public function getSuccessRateAttribute()
    {
        if ($this->total_records > 0) {
            return round(($this->success_records / $this->total_records) * 100, 2);
        }
        return 0;
    }
}
