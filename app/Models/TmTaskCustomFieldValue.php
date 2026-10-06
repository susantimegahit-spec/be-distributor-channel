<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskCustomFieldValue extends CorporateModel
{
    protected $table = 'tm_task_custom_field_values';

    protected $fillable = [
        'task_id',
        'custom_field_id',
        'value_text',
        'value_number',
        'value_date',
        'value_json',
    ];

    protected $casts = [
        'value_number' => 'decimal:4',
        'value_date'   => 'date',
        'value_json'   => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(TmMasterCustomField::class, 'custom_field_id');
    }
}
