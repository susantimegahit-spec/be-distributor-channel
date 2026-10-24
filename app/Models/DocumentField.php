<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentField extends Model
{
    use HasFactory;

    protected $table = 'document_fields';

    protected $fillable = [
        'document_schema_id',
        'section',
        'field_code',
        'label',
        'field_type',
        'source_type',
        'source',
        'lookup_config',
        'calculation_config',
        'formatter_config',
        'ui_props',
        'is_required',
        'is_readonly',
        'is_visible',
        'sequence',
    ];

    protected $casts = [
        'lookup_config' => 'array',
        'calculation_config' => 'array',
        'formatter_config' => 'array',
        'ui_props' => 'array',
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'is_visible' => 'boolean',
        'sequence' => 'integer',
    ];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(DocumentSchema::class, 'document_schema_id');
    }
}
