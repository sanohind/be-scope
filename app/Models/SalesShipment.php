<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesShipment extends Model
{
    protected $connection = 'erp';
    protected $table = 'so_invoice_line';

    protected $fillable = [
        'year',
        'period',
        'bp_code',
        'bp_name',
        'total_delivery',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'total_delivery' => 'decimal:2',
    ];
}
