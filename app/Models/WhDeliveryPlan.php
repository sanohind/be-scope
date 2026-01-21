<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhDeliveryPlan extends Model
{
    use HasFactory;

    protected $table = 'wh_delivery_plan';

    protected $fillable = [
        'partno',
        'warehouse',
        'qty_delivery',
        'delivery_date',
    ];

    protected $casts = [
        'delivery_date' => 'date:Y-m-d',
    ];
}
