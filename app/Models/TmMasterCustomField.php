<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmMasterCustomField extends CorporateModel
{
    protected $table = 'tm_master_custom_fields';

    const UPDATED_AT = null;

    protected $fillable = [
        'space_id',
        'field_name',
        'field_key',
        'field_type',
        'options_json',
        'is_required',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_required'  => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TmTaskCustomFieldValue::class, 'custom_field_id');
    }
}
