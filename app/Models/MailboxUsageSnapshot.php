<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxUsageSnapshot extends Model
{
    use HasFactory;

    protected $table = 'mailbox_usage_snapshots';

    protected $fillable = [
        'mailbox_id',
        'usage_bytes',
        'quota_bytes',
        'usage_percentage',
        'recorded_at',
    ];

    protected $casts = [
        'usage_bytes'      => 'integer',
        'quota_bytes'      => 'integer',
        'usage_percentage' => 'decimal:2',
        'recorded_at'      => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'mailbox_id');
    }

    public function getFormattedUsageAttribute(): string
    {
        return Mailbox::formatBytes($this->usage_bytes);
    }
}
