<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxQuotaRecommendation extends Model
{
    use HasFactory;

    protected $table = 'mailbox_quota_recommendations';

    protected $fillable = [
        'mailbox_id',
        'current_quota_bytes',
        'recommended_quota_bytes',
        'additional_quota_bytes',
        'reason',
        'status',
        'growth_rate_bytes_per_day',
        'estimated_days_to_full',
        'risk_level',
    ];

    protected $casts = [
        'current_quota_bytes'       => 'integer',
        'recommended_quota_bytes'   => 'integer',
        'additional_quota_bytes'    => 'integer',
        'growth_rate_bytes_per_day' => 'integer',
        'estimated_days_to_full'    => 'integer',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'mailbox_id');
    }

    public function getFormattedCurrentQuotaAttribute(): string
    {
        return Mailbox::formatBytes($this->current_quota_bytes);
    }

    public function getFormattedRecommendedQuotaAttribute(): string
    {
        return Mailbox::formatBytes($this->recommended_quota_bytes);
    }

    public function getFormattedAdditionalQuotaAttribute(): string
    {
        return Mailbox::formatBytes($this->additional_quota_bytes);
    }

    public function getFormattedGrowthRateAttribute(): string
    {
        return Mailbox::formatBytes($this->growth_rate_bytes_per_day) . '/hari';
    }
}
