<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicklistItem extends Model
{
    use HasFactory;

    protected $table = 'picklist_items';

    protected $fillable = [
        'picklist_id',
        'sales_order_id',
        'sales_order_detail_id',
        'item_code',
        'item_name',
        'whs_code',
        'unit_msr',
        'ordered_qty',
        'pick_qty',
        'unit_weight',
        'total_weight',
    ];

    protected $casts = [
        'picklist_id'           => 'integer',
        'sales_order_id'        => 'integer',
        'sales_order_detail_id' => 'integer',
        'ordered_qty'           => 'decimal:4',
        'pick_qty'              => 'decimal:4',
        'unit_weight'           => 'decimal:4',
        'total_weight'          => 'decimal:4',
    ];

    /**
     * Get the parent picklist.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Get the related sales order.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * Get the related sales order line detail.
     */
    public function salesOrderDetail(): BelongsTo
    {
        return $this->belongsTo(SalesOrderDetail::class, 'sales_order_detail_id');
    }

    /**
     * Get the related master item.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * Get the related warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'whs_code', 'whs_code');
    }
}
