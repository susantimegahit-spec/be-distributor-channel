<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmMasterStatus extends CorporateModel
{
    protected $table = 'tm_master_statuses';

    protected $fillable = [
        'space_id',
        'status_name',
        'status_category',
        'color_hex',
        'sort_order',
        'is_default',
        'is_closed_status',
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'is_default'       => 'boolean',
        'is_closed_status' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'status_id');
    }
}
