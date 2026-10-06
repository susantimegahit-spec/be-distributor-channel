<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TmMasterTaskType extends CorporateModel
{
    protected $table = 'tm_master_task_types';

    protected $fillable = [
        'type_code',
        'type_name',
        'icon_name',
        'color_hex',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'task_type_id');
    }
}
