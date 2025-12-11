<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPlan extends Model
{
    use HasFactory;

    protected $table = 'production_plan';

    protected $fillable = [
        'partno',
        'divisi',
        'qty_plan',
        'plan_date',
    ];

    protected $casts = [
        'plan_date' => 'date',
    ];
}
