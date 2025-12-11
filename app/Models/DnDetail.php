<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DnDetail extends Model
{
    // use HasFactory;

    protected $connection = 'erp';
    protected $table = 'dn_detail';

    protected $fillable = [
        'no_dn',
        'dn_line',
        'dn_supplier',
        'dn_create_date',
        'dn_year',
        'dn_period',
        'plan_delivery_date',
        'plan_delivery_time',
        'order_origin',
        'no_order',
        'order_set',
        'order_line',
        'order_seq',
        'part_no',
        'item_desc_a',
        'item_desc_b',
        'supplier_item_no',
        'lot_number',
        'dn_qty',
        'receipt_qty',
        'dn_unit',
        'dn_snp',
        'reference',
        'actual_receipt_date',
        'actual_receipt_time',
        'warehouse',
        'status_code',
        'status_desc',
    ];

    protected $casts = [
        'dn_create_date' => 'datetime',
        'plan_delivery_date' => 'date',
        'actual_receipt_date' => 'date',
    ];
}

