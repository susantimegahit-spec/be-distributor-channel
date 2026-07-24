<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_return_details';

    protected $fillable = [
        'sales_return_id',
        'sales_order_detail_id',
        'item_code',
        'quantity',
        'do_quantity',
        'unit_msr',
        'uom_entry',
        'unit_price',
        'line_total',
        'reason',
        'do_num',
        'baseline',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'do_quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function salesOrderDetail(): BelongsTo
    {
        return $this->belongsTo(SalesOrderDetail::class, 'sales_order_detail_id');
    }
}
