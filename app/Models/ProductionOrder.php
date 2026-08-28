<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_orders';

    protected $fillable = [
        'doc_entry',
        'doc_num',
        'series',
        'prod_order_no',
        'status',
        'type',
        'item_code',
        'planned_qty',
        'cmplt_qty',
        'receipt_qty',
        'rjct_qty',
        'warehouse',
        'priority',
        'project',
        'post_date',
        'start_date',
        'due_date',
        'origin_type',
        'origin_num',
        'card_code',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'u_shift',
        'u_unit',
        'comments',
        'issue_for_production',
        'receipt_from_production',
        'production_bom_id',
        'sap_status',
        'sap_error',
        'integrated_at',
        'created_by',
        'updated_by',
        'act_item_cost',
        'act_res_cost',
        'act_add_cost',
        'act_prod_cost',
        'act_by_prod_cost',
        'total_variance',
        'jrnl_memo',
        'ref_doc',
        'act_close_date',
        'overdue',
    ];

    protected $casts = [
        'doc_entry' => 'integer',
        'planned_qty' => 'decimal:4',
        'cmplt_qty' => 'decimal:4',
        'receipt_qty' => 'decimal:4',
        'rjct_qty' => 'decimal:4',
        'priority' => 'integer',
        'post_date' => 'date',
        'start_date' => 'date',
        'due_date' => 'date',
        'act_close_date' => 'date',
        'production_bom_id' => 'integer',
        'integrated_at' => 'datetime',
        'act_item_cost' => 'decimal:4',
        'act_res_cost' => 'decimal:4',
        'act_add_cost' => 'decimal:4',
        'act_prod_cost' => 'decimal:4',
        'act_by_prod_cost' => 'decimal:4',
        'total_variance' => 'decimal:4',
        'overdue' => 'integer',
    ];

    protected $appends = [
        'product_name',
        'uom',
        'warehouse_name',
        'ocr_name',
        'ocr_name2',
        'ocr_name3',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class, 'production_order_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProductionIssue::class, 'production_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProductionReceipt::class, 'production_order_id');
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    public function warehouseModel(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse', 'whs_code');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function ocr(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code', 'ocr_code');
    }

    public function ocr2(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code2', 'ocr_code');
    }

    public function ocr3(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code3', 'ocr_code');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getProductNameAttribute(): ?string
    {
        return $this->parentItem?->item_name;
    }

    public function getUomAttribute(): ?string
    {
        if (!empty($this->parentItem?->sal_unit_msr)) {
            return $this->parentItem->sal_unit_msr;
        }
        if (!empty($this->bom?->u_unit)) {
            return $this->bom->u_unit;
        }
        try {
            $prodUom = \App\Models\ProductionItem::where('item_code', $this->item_code)->value('invntry_uom');
            if (!empty($prodUom)) {
                return (string) $prodUom;
            }
        } catch (\Exception $e) {}

        try {
            $itemUom = \App\Models\Item::where('item_code', $this->item_code)->value('sal_unit_msr');
            if (!empty($itemUom)) {
                return (string) $itemUom;
            }
        } catch (\Exception $e) {}

        return null;
    }

    public function getWarehouseNameAttribute(): ?string
    {
        return $this->warehouseModel?->whs_name;
    }

    public function getOcrNameAttribute(): ?string
    {
        return $this->ocr?->ocr_name;
    }

    public function getOcrName2Attribute(): ?string
    {
        return $this->ocr2?->ocr_name;
    }

    public function getOcrName3Attribute(): ?string
    {
        return $this->ocr3?->ocr_name;
    }
}
