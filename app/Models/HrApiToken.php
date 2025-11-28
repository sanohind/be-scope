<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrApiToken extends Model
{
    protected $table = 'hr_api_tokens';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'token_created_at',
        'expired_at',
    ];

    protected $casts = [
        'token_created_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Get the latest token
     */
    public static function getLatest()
    {
        return self::latest('id')->first();
    }

    /**
     * Check if token is expired
     */
    public function isExpired()
    {
        if (!$this->expired_at) {
            return true;
        }
        return now()->greaterThan($this->expired_at);
    }
}
