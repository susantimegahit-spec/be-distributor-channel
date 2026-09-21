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
        'customer_monthly_order_id',
        'distributor_id',
        'card_code',
        'customer_name',
        'po_number',
        'doc_date',
        'doc_due_date',
        'req_due_date',
        'eta_date',
        'logistic_status',
        'proposed_delivery_date',
        'proposed_eta_date',
        'logistic_notes',
        'logistic_action_at',
        'logistic_action_by',
        'to_whs_code',
        'nopol',
        'nama_supir',
        'sap_it_doc_entry',
        'sap_it_doc_num',
        'sap_it_status',
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
        'sales_pic_id',
        'use_balance',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'doc_due_date' => 'date',
        'req_due_date' => 'date',
        'eta_date' => 'date',
        'proposed_delivery_date' => 'date',
        'proposed_eta_date' => 'date',
        'logistic_action_at' => 'datetime',
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
        'use_balance' => 'integer',
    ];

    protected $appends = [
        'sales_employee_name',
        'total_order',
        'total_discount',
        'grand_total',
        'depo',
        'so_number',
        'order_number',
    ];

    /**
     * Get the distributor depo name/code.
     */
    public function getDepoAttribute(): ?string
    {
        if ($this->relationLoaded('distributor') && $this->distributor?->depo) {
            return $this->distributor->depo;
        }

        if ($this->distributor?->depo) {
            return $this->distributor->depo;
        }

        if (!empty($this->card_code)) {
            return Distributor::where('code_customer', $this->card_code)->value('depo');
        }

        return null;
    }

    /**
     * Get SO number formatted from sap_doc_num with fallback to order_no.
     */
    public function getSoNumberAttribute(): ?string
    {
        return $this->sap_doc_num ?: $this->order_no;
    }

    /**
     * Get order number formatted from sap_doc_num with fallback to order_no.
     */
    public function getOrderNumberAttribute(): ?string
    {
        return $this->sap_doc_num ?: $this->order_no;
    }

    /**
     * Get the total order amount from item lines before discount.
     */
    public function getTotalOrderAttribute(): float
    {
        if ($this->relationLoaded('details') && $this->details->isNotEmpty()) {
            $total = (float) $this->details->sum(function ($line) {
                return (float) $line->quantity * (float) $line->unit_price;
            });
            if ($total > 0) {
                return $total;
            }
        }

        $dbTotal = (float) $this->details()->selectRaw('SUM(quantity * unit_price) as total')->value('total');
        if ($dbTotal > 0) {
            return $dbTotal;
        }

        return (float) $this->doc_total;
    }

    /**
     * Get the total discount amount.
     */
    public function getTotalDiscountAttribute(): float
    {
        if ($this->relationLoaded('sapDiscount') && $this->sapDiscount) {
            if ($this->sapDiscount->relationLoaded('details')) {
                return (float) $this->sapDiscount->details->sum('total_discount');
            }
            return (float) $this->sapDiscount->details()->sum('total_discount');
        }

        if ($this->sapDiscount) {
            return (float) $this->sapDiscount->details()->sum('total_discount');
        }

        return 0.0;
    }

    /**
     * Get the grand total (total_order - total_discount).
     */
    public function getGrandTotalAttribute(): float
    {
        $totalOrder = $this->total_order;
        $totalDiscount = $this->total_discount;

        return max(0.0, $totalOrder - $totalDiscount);
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
     * Get the Customer Monthly Order that spawned this sales order.
     */
    public function customerMonthlyOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CustomerMonthlyOrder::class, 'customer_monthly_order_id');
    }

    /**
     * Get the current approval stage of the order.
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id');
    }

    /**
     * Get the Admin Sales user who approved/handled this sales order.
     */
    public function salesPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_pic_id');
    }

    /**
     * Get the approval histories of the order.
     */
    public function approvalHistories(): HasMany
    {
        return $this->hasMany(SalesOrderApprovalHistory::class);
    }

    /**
     * Get the logistic activity logs for the order.
     */
    public function logisticLogs(): HasMany
    {
        return $this->hasMany(SalesOrderLogisticLog::class, 'sales_order_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest logistic log.
     */
    public function latestLogisticLog()
    {
        return $this->hasOne(SalesOrderLogisticLog::class, 'sales_order_id')->latestOfMany();
    }

    /**
     * Get the picklist items for the sales order.
     */
    public function picklistItems(): HasMany
    {
        return $this->hasMany(PicklistItem::class, 'sales_order_id');
    }

    /**
     * Get the total doc total after discount.
     */
    public function getDocTotalAfterDiscountAttribute(): float
    {
        return $this->grand_total;
    }

    /**
     * Convert the model instance to an array to include total_order, total_discount, grand_total, and DocTotal.
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['total_order'] = $this->total_order;
        $array['totalOrder'] = $this->total_order;
        $array['total_discount'] = $this->total_discount;
        $array['totalDiscount'] = $this->total_discount;
        $array['grand_total'] = $this->grand_total;
        $array['grandTotal'] = $this->grand_total;

        // Ensure doc_total reflects gross total of items for frontend compatibility
        $array['doc_total'] = (string) $this->total_order;
        $array['docTotal'] = $this->total_order;
        $array['DocTotal'] = $this->grand_total;

        return $array;
    }
}
