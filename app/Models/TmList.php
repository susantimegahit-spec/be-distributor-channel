<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmList extends CorporateModel
{
    protected $table = 'tm_lists';

    protected $fillable = [
        'space_id',
        'folder_id',
        'list_name',
        'description',
        'color_hex',
        'default_view',
        'sort_order',
        'is_archived',
        'created_by_user_id',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'is_archived' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(TmFolder::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'list_id')->orderBy('sort_order');
    }
}
