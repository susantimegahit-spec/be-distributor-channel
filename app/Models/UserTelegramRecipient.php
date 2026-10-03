<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTelegramRecipient extends Model
{
    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'recipient_name',
        'chat_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the telegram recipient.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
