<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskTimeTracking extends CorporateModel
{
    protected $table = 'tm_task_time_trackings';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'employee_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'note',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'employee_id');
    }
}
