<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmSpace extends CorporateModel
{
    protected $table = 'tm_spaces';

    protected $fillable = [
        'workspace_id',
        'department_id',
        'space_name',
        'space_slug',
        'color_hex',
        'icon_name',
        'description',
        'is_private',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TmWorkspace::class, 'workspace_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrisDepartment::class, 'department_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TmSpaceMember::class, 'space_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(TmFolder::class, 'space_id')->orderBy('sort_order');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TmList::class, 'space_id')->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'space_id');
    }

    public function customStatuses(): HasMany
    {
        return $this->hasMany(TmMasterStatus::class, 'space_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(TmMasterTag::class, 'space_id');
    }
}
