<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskDependency extends CorporateModel
{
    protected $table = 'tm_task_dependencies';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'dependency_type',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'depends_on_task_id');
    }
}
