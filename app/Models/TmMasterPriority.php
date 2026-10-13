<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TmMasterPriority extends CorporateModel
{
    protected $table = 'tm_master_priorities';

    protected $fillable = [
        'priority_code',
        'priority_name',
        'color_hex',
        'level_weight',
        'target_sla_hours',
    ];

    protected $casts = [
        'level_weight'     => 'integer',
        'target_sla_hours' => 'integer',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'priority_id');
    }
}
