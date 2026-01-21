<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsakaiTarget extends Model
{
    protected $fillable = [
        'asakai_title_id',
        'year',
        'period',
        'target',
    ];

    protected $casts = [
        'year' => 'integer',
        'period' => 'integer',
        'target' => 'integer',
    ];
}
