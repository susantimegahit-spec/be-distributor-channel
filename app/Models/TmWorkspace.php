<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmWorkspace extends CorporateModel
{
    protected $table = 'tm_workspaces';

    protected $fillable = [
        'workspace_code',
        'name',
        'description',
        'logo_url',
        'owner_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(TmSpace::class, 'workspace_id');
    }
}
