<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'item_code',
        'quantity',
        'unit_msr',
        'uom_entry',
        'whs_code',
        'unit_price',
        'disc_percent',
        'vat_group',
        'line_total',
        'free_text',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'disc_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $appends = [
        'item_name',
        'whs_name',
        'vat_name',
        'ocr_name',
        'ocr_name2',
        'ocr_name3',
    ];

    /**
     * Get the sales order that owns the detail.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the item associated with the detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * Get the warehouse associated with the detail.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'whs_code', 'whs_code');
    }

    /**
     * Get the VAT associated with the detail.
     */
    public function vat(): BelongsTo
    {
        return $this->belongsTo(Vat::class, 'vat_group', 'code');
    }

    /**
     * Get the OCR code associated with the detail.
     */
    public function ocr(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code', 'ocr_code');
    }

    /**
     * Get the OCR code 2 associated with the detail.
     */
    public function ocr2(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code2', 'ocr_code');
    }

    /**
     * Get the OCR code 3 associated with the detail.
     */
    public function ocr3(): BelongsTo
    {
        return $this->belongsTo(OcrCode::class, 'ocr_code3', 'ocr_code');
    }

    /**
     * Accessor for item name.
     */
    public function getItemNameAttribute(): ?string
    {
        return $this->item?->item_name;
    }

    /**
     * Accessor for warehouse name.
     */
    public function getWhsNameAttribute(): ?string
    {
        return $this->warehouse?->whs_name;
    }

    /**
     * Accessor for VAT name.
     */
    public function getVatNameAttribute(): ?string
    {
        return $this->vat?->name;
    }

    /**
     * Accessor for OCR name.
     */
    public function getOcrNameAttribute(): ?string
    {
        return $this->ocr?->ocr_name;
    }

    /**
     * Accessor for OCR name 2.
     */
    public function getOcrName2Attribute(): ?string
    {
        return $this->ocr2?->ocr_name;
    }

    /**
     * Accessor for OCR name 3.
     */
    public function getOcrName3Attribute(): ?string
    {
        return $this->ocr3?->ocr_name;
    }
}
