<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockByWhSnapshot extends Model
{
    protected $table = 'stock_by_wh_snapshots';

    protected $fillable = [
        'snapshot_date',
        'warehouse',
        'partno',
        'desc',
        'partname',
        'oldpartno',
        'group',
        'groupkey',
        'product_type',
        'model',
        'customer',
        'onhand',
        'allocated',
        'onorder',
        'economicstock',
        'safety_stock',
        'min_stock',
        'max_stock',
        'unit',
        'location',
        'group_type',
        'group_type_desc',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'snapshot_date' => 'date',
        'created_at' => 'datetime',
    ];
}


