<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyUseWh extends Model
{
    use HasFactory;

    protected $table = 'daily_use_wh';

    protected $fillable = [
        'partno',
        'warehouse',
        'year',
        'period',
        'qty',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'qty' => 'integer',
    ];
}

