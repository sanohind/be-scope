<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyUseWhMinMax extends Model
{
    protected $table = 'daily_use_wh_min_max';

    protected $fillable = [
        'warehouse',
        'year',
        'period',
        'min_onhand',
        'max_onhand',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'min_onhand' => 'integer',
        'max_onhand' => 'integer',
    ];
}
