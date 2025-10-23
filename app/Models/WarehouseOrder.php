<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseOrder extends Model
{
    protected $table = 'view_warehouse_order';
    public $timestamps = false;

    protected $fillable = [
        'order_origin_code',
        'order_origin',
        'trx_type',
        'order_date',
        'plan_delivery_date',
        'ship_from_type',
        'ship_from',
        'ship_from_desc',
        'ship_to_type',
        'ship_to',
        'ship_to_desc',
    ];
}
