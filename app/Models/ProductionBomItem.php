<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBomItem extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_bom_items';

    public function getTable()
    {
        $conn = config('database.connections.' . ($this->connection ?: config('database.default')));
        if (($conn['driver'] ?? '') === 'sqlite' || config('database.default') === 'sqlite') {
            return 'production_bom_items';
        }
        return parent::getTable();
    }

    protected $fillable = [
        'production_bom_id',
        'father',
        'child_num',
        'type',
        'code',
        'quantity',
        'warehouse',
        'issue_mthd',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'comments',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'child_num' => 'integer',
    ];

    protected $appends = [
        'product_name',
        'uom',
        'ocr_name',
        'ocr_name2',
        'ocr_name3',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function warehouseModel(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse', 'whs_code');
    }

    /**
     * Get the item associated with the component (if type is Item).
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'code', 'item_code');
    }

    /**
     * Get the resource associated with the component (if type is Resource).
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProductionResource::class, 'code', 'res_code');
    }

    /**
     * Get the OCR code 1 associated with the component.
     */
    public function ocr(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code', 'ocr_code');
    }

    /**
     * Get the OCR code 2 associated with the component.
     */
    public function ocr2(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code2', 'ocr_code');
    }

    /**
     * Get the OCR code 3 associated with the component.
     */
    public function ocr3(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code3', 'ocr_code');
    }

    /**
     * Accessor for product/component name.
     */
    public function getProductNameAttribute(): ?string
    {
        if ($this->type === 'Resource' || $this->type === '290') {
            return $this->resource?->res_name;
        }
        return $this->item?->item_name;
    }

    /**
     * Accessor for component Unit of Measure (UOM).
     */
    public function getUomAttribute(): ?string
    {
        if ($this->type === 'Resource' || $this->type === '290') {
            return $this->resource?->unit_of_msr;
        }
        return $this->item?->invntry_uom;
    }

    /**
     * Accessor for OCR name 1 (Cabang).
     */
    public function getOcrNameAttribute(): ?string
    {
        return $this->ocr?->ocr_name;
    }

    /**
     * Accessor for OCR name 2 (Bisnis Unit).
     */
    public function getOcrName2Attribute(): ?string
    {
        return $this->ocr2?->ocr_name;
    }

    /**
     * Accessor for OCR name 3 (Department).
     */
    public function getOcrName3Attribute(): ?string
    {
        return $this->ocr3?->ocr_name;
    }
}
