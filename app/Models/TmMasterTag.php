<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TmMasterTag extends CorporateModel
{
    protected $table = 'tm_master_tags';

    const UPDATED_AT = null;

    protected $fillable = [
        'space_id',
        'tag_name',
        'color_hex',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(TmTask::class, 'tm_task_tags', 'tag_id', 'task_id');
    }
}
