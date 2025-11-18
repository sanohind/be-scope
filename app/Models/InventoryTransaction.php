<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $connection = 'mysql';
    protected $table = 'inventory_transaction';
    public $timestamps = false;

    protected $fillable = [
        'partno',
        'part_desc',
        'std_oldpart',
        'warehouse',
        'trans_date',
        'trans_date2',
        'lotno',
        'trans_id',
        'qty',
        'qty_hand',
        'trans_type',
        'order_type',
        'order_no',
        'receipt',
        'shipment',
        'user',
    ];
}
