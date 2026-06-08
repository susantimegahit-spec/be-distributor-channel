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

    /**
     * Get the sales order that owns the detail.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
