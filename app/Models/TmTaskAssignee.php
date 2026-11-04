<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskAssignee extends CorporateModel
{
    protected $table = 'tm_task_assignees';

    const UPDATED_AT = null;
    const CREATED_AT = 'assigned_at';

    protected $fillable = [
        'task_id',
        'employee_id',
        'assigned_by_employee_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'employee_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'assigned_by_employee_id');
    }
}
