<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdReport extends Model
{
    protected $connection = 'erp';
    protected $table = 'prod_report';
    public $timestamps = false;

    protected $fillable = [
        'prod_index',
        'production_order',
        'part_number',
        'old_part',
        'divisi',
        'lot_number',
        'qty_pelaporan',
        'snp',
        'trans_date_month',
        'trans_date_year',
        'qty_planning',
        'planning_date',
        'transaction_date',
        'trans_date',
        'Status',
        'user_login',
        'user name',
    ];

    protected $casts = [
        'prod_index' => 'integer',
        'qty_pelaporan' => 'decimal:2',
        'snp' => 'decimal:2',
        'qty_planning' => 'decimal:2',
        'trans_date_month' => 'integer',
        'trans_date_year' => 'integer',
        'planning_date' => 'date',
        'transaction_date' => 'date',
        'trans_date' => 'date',
    ];
}

