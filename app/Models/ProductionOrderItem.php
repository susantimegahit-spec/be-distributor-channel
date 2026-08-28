<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderItem extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_order_items';

    protected $fillable = [
        'production_order_id',
        'line_num',
        'item_code',
        'type',
        'base_qty',
        'planned_qty',
        'issued_qty',
        'warehouse',
        'issue_mthd',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'comments',
        'available_qty',
        'base_entry',
        'base_type',
        'base_line',
    ];

    protected $casts = [
        'production_order_id' => 'integer',
        'line_num' => 'integer',
        'base_qty' => 'decimal:4',
        'planned_qty' => 'decimal:4',
        'issued_qty' => 'decimal:4',
        'available_qty' => 'decimal:4',
        'base_entry' => 'integer',
        'base_type' => 'integer',
        'base_line' => 'integer',
    ];

    protected $appends = [
        'product_name',
        'uom',
        'warehouse_name',
        'ocr_name',
        'ocr_name2',
        'ocr_name3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'item_code', 'item_code');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProductionResource::class, 'item_code', 'res_code');
    }

    public function warehouseModel(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse', 'whs_code');
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

    public function getProductNameAttribute(): ?string
    {
        if ($this->type === 'Resource' || $this->type === '290') {
            return $this->resource?->res_name;
        }
        return $this->item?->item_name;
    }

    public function getUomAttribute(): ?string
    {
        if ($this->type === 'Resource' || $this->type === '290' || $this->type === 'R') {
            return $this->resource?->unit_of_msr;
        }
        if (!empty($this->item?->invntry_uom)) {
            return $this->item->invntry_uom;
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

        try {
            $bomUom = \App\Models\ProductionBomItem::where('item_code', $this->item_code)->value('uom');
            if (!empty($bomUom)) {
                return (string) $bomUom;
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
