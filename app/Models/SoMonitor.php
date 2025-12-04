<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoMonitor extends Model
{
    protected $connection = 'erp';
    protected $table = 'so_monitor';

    protected $fillable = [
        'year',
        'period',
        'bp_code',
        'bp_name',
        'total_po',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'total_po' => 'decimal:2',
    ];
}
