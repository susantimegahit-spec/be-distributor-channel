<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use HasFactory;

    protected $table = 'sales_returns';

    protected $fillable = [
        'return_no',
        'sales_order_id',
        'distributor_id',
        'card_code',
        'customer_name',
        'reason',
        'doc_total',
        'status',
        'sap_doc_entry',
        'sap_doc_num',
        'sap_error',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalesReturnDetail::class, 'sales_return_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SalesReturnAttachment::class, 'sales_return_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
