<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'command',
        'expression',
        'description',
        'is_active',
        'last_run_at',
        'last_run_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /**
     * Get logs for the cron job.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CronJobLog::class, 'cron_job_id');
    }
}
