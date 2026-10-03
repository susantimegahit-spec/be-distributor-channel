<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderApprovalHistory extends Model
{
    use HasFactory;

    protected $table = 'sales_order_approval_histories';

    protected $fillable = [
        'sales_order_id',
        'approval_id_before',
        'approval_id_after',
        'action',
        'user_id',
        'notes',
    ];

    /**
     * Get the sales order associated with the history log.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the approval stage before the action.
     */
    public function approvalBefore(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id_before');
    }

    /**
     * Get the approval stage after the action.
     */
    public function approvalAfter(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id_after');
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
