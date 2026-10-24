<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskActivityLog extends CorporateModel
{
    protected $table = 'tm_task_activity_logs';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'performed_by_employee_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'performed_by_employee_id');
    }
}
