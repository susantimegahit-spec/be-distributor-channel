<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLogisticLog extends Model
{
    use HasFactory;

    protected $table = 'sales_order_logistic_logs';

    protected $fillable = [
        'sales_order_id',
        'action',
        'from_status',
        'to_status',
        'previous_due_date',
        'previous_eta_date',
        'proposed_due_date',
        'proposed_eta_date',
        'approved_due_date',
        'approved_eta_date',
        'notes',
        'user_id',
        'user_name',
        'role_name',
        'sap_it_doc_entry',
        'sap_it_doc_num',
    ];

    protected $casts = [
        'previous_due_date' => 'date',
        'previous_eta_date' => 'date',
        'proposed_due_date' => 'date',
        'proposed_eta_date' => 'date',
        'approved_due_date' => 'date',
        'approved_eta_date' => 'date',
    ];

    /**
     * Get the sales order that owns the log.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
