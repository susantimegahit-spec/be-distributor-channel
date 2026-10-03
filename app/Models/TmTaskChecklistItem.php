<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskChecklistItem extends CorporateModel
{
    protected $table = 'tm_task_checklist_items';

    const UPDATED_AT = null;

    protected $fillable = [
        'checklist_id',
        'item_text',
        'is_completed',
        'assignee_employee_id',
        'due_date',
        'completed_at',
        'completed_by_employee_id',
        'sort_order',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'sort_order'   => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TmTaskChecklist::class, 'checklist_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'assignee_employee_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'completed_by_employee_id');
    }
}
