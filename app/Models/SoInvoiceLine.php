<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoInvoiceLine extends Model
{
    protected $connection = 'mysql';
    protected $table = 'so_invoice_line';
    public $timestamps = false;

    protected $fillable = [
        'bp_code',
        'bp_name',
        'sales_order',
        'so_date',
        'so_line',
        'so_sequence',
        'customer_po',
        'shipment',
        'shipment_line',
        'delivery_date',
        'receipt',
        'receipt_line',
        'receipt_date',
        'part_no',
        'old_partno',
        'product_type',
        'cust_partno',
        'cust_partname',
        'item_group',
        'delivered_qty',
        'unit',
        'shipment_reference',
        'status',
        'shipment_status',
        'invoice_no',
        'inv_line',
        'invoice_date',
        'invoice_qty',
        'currency',
        'price',
        'amount',
        'price_hc',
        'amount_hc',
        'inv_stat',
        'invoice_status',
        'dlv_log_date',
    ];
}
