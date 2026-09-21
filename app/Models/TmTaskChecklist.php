<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmTaskChecklist extends CorporateModel
{
    protected $table = 'tm_task_checklists';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'checklist_title',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TmTaskChecklistItem::class, 'checklist_id')->orderBy('sort_order');
    }
}
