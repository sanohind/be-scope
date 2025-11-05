<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockByWh extends Model
{
    protected $connection = 'erp';
    protected $table = 'stockbywh';
    public $timestamps = false;

    protected $fillable = [
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
    ];
}
