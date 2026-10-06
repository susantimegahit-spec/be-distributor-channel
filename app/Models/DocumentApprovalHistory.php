<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApprovalHistory extends Model
{
    use HasFactory;

    protected $table = 'document_approval_histories';

    protected $fillable = [
        'document_approval_id',
        'user_id',
        'user_name',
        'user_role',
        'level',
        'stage_name',
        'action',
        'notes',
        'payload_snapshot',
        'action_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'payload_snapshot' => 'array',
        'action_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(DocumentApproval::class, 'document_approval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
