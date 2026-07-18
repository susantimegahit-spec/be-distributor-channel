<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMonthlyOrder extends Model
{
    use HasFactory;

    protected $table = 'customer_monthly_orders';

    protected $guarded = [];

    protected $casts = [
        'doc_date' => 'date',
        'doc_due_date' => 'date',
        'eta_date' => 'date',
        'disc_percent' => 'decimal:2',
        'doc_total' => 'decimal:2',
        'approval_id' => 'integer',
        'submitted_at' => 'datetime',
        'integrated_at' => 'datetime',
        'delivery_date' => 'datetime',
        'arrived_date' => 'datetime',
        'sap_last_synced_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'series' => 'integer',
    ];

    protected $appends = [
        'sales_employee_name',
        'total_discount',
        'depo',
    ];

    /**
     * Get the distributor depo name/code.
     */
    public function getDepoAttribute(): ?string
    {
        return $this->distributor?->depo;
    }

    /**
     * Get the total discount amount.
     */
    public function getTotalDiscountAttribute(): float
    {
        if (!$this->sapDiscount) {
            return 0.0;
        }

        return (float) $this->sapDiscount->details->sum('total_discount');
    }

    /**
     * Get the sales employee name.
     */
    public function getSalesEmployeeNameAttribute(): ?string
    {
        return $this->salesEmployee?->slp_name;
    }

    /**
     * Get the details for the customer monthly order.
     */
    public function details(): HasMany
    {
        return $this->hasMany(CustomerMonthlyOrderDetail::class, 'customer_monthly_order_id');
    }

    /**
     * Get the attachments (always empty for duplicate draft CMO).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SalesOrderAttachment::class, 'sales_order_id', 'id');
    }

    /**
     * Get the distributor that owns the order.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    /**
     * Get the SAP discount header associated with the order.
     */
    public function sapDiscount(): BelongsTo
    {
        return $this->belongsTo(SapDiscountHeader::class, 'id_discount', 'discount_code');
    }

    /**
     * Get the sales employee associated with the order.
     */
    public function salesEmployee(): BelongsTo
    {
        return $this->belongsTo(SalesEmployee::class, 'slp_code', 'slp_code');
    }
}
