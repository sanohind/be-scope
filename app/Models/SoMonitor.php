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
        'order_qty',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'order_qty' => 'decimal:2',
    ];
}
