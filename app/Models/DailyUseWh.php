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
        'daily_use',
        'plan_date',
    ];

    protected $casts = [
        'plan_date' => 'date',
    ];
}

