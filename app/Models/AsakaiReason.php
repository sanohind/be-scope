<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsakaiReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'asakai_chart_id',
        'date',
        'part_no',
        'part_name',
        'problem',
        'qty',
        'section',
        'line',
        'penyebab',
        'perbaikan',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the chart for this reason.
     */
    public function asakaiChart()
    {
        return $this->belongsTo(AsakaiChart::class);
    }

    /**
     * Get the user who created this reason.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
