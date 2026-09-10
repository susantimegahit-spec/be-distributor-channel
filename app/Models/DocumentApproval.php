<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentApproval extends Model
{
    use HasFactory;

    protected $table = 'document_approvals';

    protected $fillable = [
        'document_type_id',
        'sap_object_type',
        'sap_doc_entry',
        'sap_doc_num',
        'requester_id',
        'requester_name',
        'status',
        'current_level',
        'max_level',
        'doc_date',
        'doc_due_date',
        'total_amount',
        'currency',
        'notes',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'sap_object_type' => 'integer',
        'sap_doc_entry' => 'integer',
        'current_level' => 'integer',
        'max_level' => 'integer',
        'total_amount' => 'decimal:4',
        'doc_date' => 'date',
        'doc_due_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentApprovalHistory::class, 'document_approval_id')->orderBy('action_at', 'asc');
    }
}
