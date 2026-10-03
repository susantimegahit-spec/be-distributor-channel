<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    protected $table = 'approval_workflows';

    protected $fillable = [
        'document_type_id',
        'name',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:4',
        'max_amount' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStage::class, 'approval_workflow_id')->orderBy('level', 'asc');
    }
}
