<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cron_job_id',
        'run_at',
        'finished_at',
        'status',
        'duration_seconds',
        'message',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get the cron job that owns the log.
     */
    public function cronJob(): BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'cron_job_id');
    }
}
