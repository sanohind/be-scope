<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStockSummary extends Model
{
    protected $table = 'warehouse_stock_summaries';

    protected $fillable = [
        'warehouse',
        'granularity',
        'period_start',
        'period_end',
        'onhand_total',
        'receipt_total',
        'issue_total',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'onhand_total' => 'int',
        'receipt_total' => 'int',
        'issue_total' => 'int',
    ];
}



