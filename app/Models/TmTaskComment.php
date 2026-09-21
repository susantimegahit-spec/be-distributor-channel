<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmTaskComment extends CorporateModel
{
    protected $table = 'tm_task_comments';

    protected $fillable = [
        'task_id',
        'parent_comment_id',
        'author_employee_id',
        'comment_text',
        'is_internal_only',
    ];

    protected $casts = [
        'is_internal_only' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TmTaskComment::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TmTaskComment::class, 'parent_comment_id')->with('author');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'author_employee_id');
    }
}
