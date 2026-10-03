<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSchema extends Model
{
    use HasFactory;

    protected $table = 'document_schemas';

    protected $fillable = [
        'document_type_id',
        'version',
        'name',
        'layout_config',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'document_schema_id')->orderBy('sequence', 'asc');
    }

    public function headerFields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'document_schema_id')
            ->where('section', 'header')
            ->where('is_visible', true)
            ->orderBy('sequence', 'asc');
    }

    public function lineFields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'document_schema_id')
            ->where('section', 'line')
            ->where('is_visible', true)
            ->orderBy('sequence', 'asc');
    }

    public function summaryFields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'document_schema_id')
            ->where('section', 'summary')
            ->where('is_visible', true)
            ->orderBy('sequence', 'asc');
    }
}
