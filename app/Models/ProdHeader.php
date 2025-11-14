<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdHeader extends Model
{
    protected $connection = 'erp';
    protected $table = 'view_prod_header';
    public $timestamps = false;

    protected $fillable = [
        'prod_index',
        'prod_no',
        'planning_date',
        'item',
        'old_partno',
        'description',
        'mat_desc',
        'customer',
        'model',
        'unique_no',
        'sanoh_code',
        'snp',
        'sts',
        'status',
        'qty_order',
        'qty_delivery',
        'qty_os',
        'warehouse',
        'divisi',
    ];
}
