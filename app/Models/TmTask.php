<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TmTask extends CorporateModel
{
    use SoftDeletes;

    protected $table = 'tm_tasks';

    protected $fillable = [
        'task_code',
        'legacy_clickup_id',
        'space_id',
        'folder_id',
        'list_id',
        'parent_task_id',
        'title',
        'description',
        'status_id',
        'priority_id',
        'task_type_id',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'progress_percentage',
        'created_by_employee_id',
        'approved_by_employee_id',
        'is_milestone',
        'is_archived',
        'sort_order',
    ];

    protected $casts = [
        'start_date'          => 'datetime',
        'due_date'            => 'datetime',
        'completed_at'        => 'datetime',
        'estimated_hours'     => 'decimal:2',
        'actual_hours'        => 'decimal:2',
        'progress_percentage' => 'integer',
        'is_milestone'        => 'boolean',
        'is_archived'         => 'boolean',
        'sort_order'          => 'integer',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(TmFolder::class, 'folder_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TmList::class, 'list_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'parent_task_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TmMasterStatus::class, 'status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TmMasterPriority::class, 'priority_id');
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TmMasterTaskType::class, 'task_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'created_by_employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'approved_by_employee_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TmTaskAssignee::class, 'task_id');
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(HrisEmployee::class, 'tm_task_assignees', 'task_id', 'employee_id')
            ->withPivot('assigned_by_employee_id', 'assigned_at');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(TmTaskWatcher::class, 'task_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TmMasterTag::class, 'tm_task_tags', 'task_id', 'tag_id');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(TmTaskCustomFieldValue::class, 'task_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TmTaskChecklist::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TmTaskAttachment::class, 'task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TmTaskComment::class, 'task_id')->whereNull('parent_comment_id')->with('replies');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(TmTaskComment::class, 'task_id');
    }

    public function timeTrackings(): HasMany
    {
        return $this->hasMany(TmTaskTimeTracking::class, 'task_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TmTaskDependency::class, 'task_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TmTaskActivityLog::class, 'task_id')->latest();
    }
}
