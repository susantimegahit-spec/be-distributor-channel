<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskWatcher extends CorporateModel
{
    protected $table = 'tm_task_watchers';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'employee_id',
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
