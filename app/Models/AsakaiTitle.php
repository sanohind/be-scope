<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsakaiTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
    ];

    /**
     * Get the charts for this title.
     */
    public function charts()
    {
        return $this->hasMany(AsakaiChart::class);
    }
}
