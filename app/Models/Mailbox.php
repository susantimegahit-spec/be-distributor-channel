<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mailbox extends Model
{
    use HasFactory;

    public const STATUS_SAFE       = 'SAFE';
    public const STATUS_MONITORING = 'MONITORING';
    public const STATUS_WARNING    = 'WARNING';
    public const STATUS_CRITICAL   = 'CRITICAL';

    protected $table = 'mailboxes';

    protected $fillable = [
        'email',
        'user_name',
        'department_id',
        'quota_bytes',
        'current_usage_bytes',
        'usage_percentage',
        'status',
        'domain',
        'suspended_incoming',
        'suspended_login',
        'last_synced_at',
    ];

    protected $casts = [
        'quota_bytes'         => 'integer',
        'current_usage_bytes' => 'integer',
        'usage_percentage'    => 'decimal:2',
        'suspended_incoming'  => 'boolean',
        'suspended_login'     => 'boolean',
        'last_synced_at'      => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(MailboxDepartment::class, 'department_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MailboxUsageSnapshot::class, 'mailbox_id')->orderBy('recorded_at', 'asc');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(MailboxQuotaRecommendation::class, 'mailbox_id')->latest();
    }

    public function latestRecommendation(): HasOne
    {
        return $this->hasOne(MailboxQuotaRecommendation::class, 'mailbox_id')->latestOfMany();
    }

    /**
     * Remaining space in bytes.
     */
    public function getRemainingBytesAttribute(): int
    {
        if ($this->quota_bytes <= 0) {
            return 0; // Unlimited
        }
        return max(0, $this->quota_bytes - $this->current_usage_bytes);
    }

    /**
     * Formatted string helpers.
     */
    public function getFormattedUsageAttribute(): string
    {
        return self::formatBytes($this->current_usage_bytes);
    }

    public function getFormattedQuotaAttribute(): string
    {
        if ($this->quota_bytes <= 0) {
            return 'Unlimited';
        }
        return self::formatBytes($this->quota_bytes);
    }

    public function getFormattedRemainingAttribute(): string
    {
        if ($this->quota_bytes <= 0) {
            return 'Unlimited';
        }
        return self::formatBytes($this->remaining_bytes);
    }

    public static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / pow(1024, $power);

        return round($value, $precision) . ' ' . $units[$power];
    }

    /**
     * Status UI color helper.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SAFE       => '#10b981', // green
            self::STATUS_MONITORING => '#3b82f6', // blue
            self::STATUS_WARNING    => '#f59e0b', // amber
            self::STATUS_CRITICAL   => '#ef4444', // red
            default                 => '#6b7280',
        };
    }
}
