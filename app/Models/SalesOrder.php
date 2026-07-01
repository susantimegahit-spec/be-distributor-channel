<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    public const STAGE_DRAFT = 1;
    public const STAGE_WAITING_OM = 2;
    public const STAGE_WAITING_ASM = 3;
    public const STAGE_WAITING_ADMIN_SALES = 4;
    public const STAGE_WAITING_FINANCE = 5;
    public const STAGE_COMPLETED = 6;

    protected $fillable = [
        'order_no',
        'distributor_id',
        'card_code',
        'customer_name',
        'po_number',
        'doc_date',
        'doc_due_date',
        'slp_code',
        'cntct_code',
        'pay_to_code',
        'address',
        'ship_to_code',
        'address2',
        'disc_percent',
        'doc_total',
        'comments',
        'id_discount',
        'series',
        'series_name',
        'status',
        'approval_id',
        'sap_doc_entry',
        'sap_doc_num',
        'sap_error',
        'sap_discount_code',
        'sap_status',
        'sap_last_doc_type',
        'sap_last_doc_num',
        'sap_last_synced_at',
        'submitted_at',
        'integrated_at',
        'delivery_date',
        'arrived_date',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'doc_due_date' => 'date',
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
     * Get the details for the sales order.
     */
    public function details(): HasMany
    {
        return $this->hasMany(SalesOrderDetail::class);
    }

    /**
     * Get the integration logs for the sales order.
     */
    public function integrationLogs(): HasMany
    {
        return $this->hasMany(SalesOrderIntegrationLog::class);
    }

    /**
     * Get the attachments for the sales order.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SalesOrderAttachment::class);
    }

    /**
     * Get the distributor that owns the sales order.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    /**
     * Get the SAP discount header associated with the sales order.
     */
    public function sapDiscount(): BelongsTo
    {
        return $this->belongsTo(SapDiscountHeader::class, 'id_discount', 'discount_code');
    }

    /**
     * Get the sales employee associated with the sales order.
     */
    public function salesEmployee(): BelongsTo
    {
        return $this->belongsTo(SalesEmployee::class, 'slp_code', 'slp_code');
    }

    /**
     * Get the sales employee name.
     */
    public function getSalesEmployeeNameAttribute(): ?string
    {
        return $this->salesEmployee?->slp_name;
    }

    /**
     * Get the current approval stage of the order.
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id');
    }

    /**
     * Get the approval histories of the order.
     */
    public function approvalHistories(): HasMany
    {
        return $this->hasMany(SalesOrderApprovalHistory::class);
    }

    /**
     * Get the total doc total after discount.
     */
    public function getDocTotalAfterDiscountAttribute(): float
    {
        $docTotal = (float) $this->doc_total;
        $totalDiscount = $this->total_discount;
        
        return max(0.0, $docTotal - $totalDiscount);
    }

    /**
     * Convert the model instance to an array to include DocTotal.
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['DocTotal'] = $this->doc_total_after_discount;
        
        return $array;
    }
}
