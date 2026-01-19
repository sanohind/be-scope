<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsakaiChart extends Model
{
    use HasFactory;

    protected $fillable = [
        'asakai_title_id',
        'date',
        'qty',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the title for this chart.
     */
    public function asakaiTitle()
    {
        return $this->belongsTo(AsakaiTitle::class);
    }

    /**
     * Get the user who created this chart.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reasons for this chart.
     */
    public function reasons()
    {
        return $this->hasMany(AsakaiReason::class);
    }
}
