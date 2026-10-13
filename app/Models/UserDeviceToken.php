<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
    ];

    /**
     * Get the user that owns the device token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
