<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseOrderLine extends Model
{
    protected $table = 'view_warehouse_order_line';
    public $timestamps = false;

    protected $fillable = [
        'order_origin_code',
        'order_origin',
        'trx_type',
        'order_date',
        'delivery_date',
        'receipt_date',
        'order_no',
        'line_no',
        'ship_from_type',
        'ship_from',
        'ship_from_desc',
        'ship_to_type',
        'ship_to',
        'ship_to_desc',
        'item_code',
        'item_desc',
        'item_desc2',
        'order_qty',
        'ship_qty',
        'unit',
        'line_status_code',
        'line_status',
    ];
}
