<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmFolder extends CorporateModel
{
    protected $table = 'tm_folders';

    protected $fillable = [
        'space_id',
        'folder_name',
        'description',
        'color_hex',
        'sort_order',
        'is_hidden',
        'is_archived',
        'created_by_user_id',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'is_hidden'   => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TmList::class, 'folder_id')->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'folder_id');
    }
}
