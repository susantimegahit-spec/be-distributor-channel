<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicklistItem extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ekspedisi.picklist_items';

    /**
     * Get the table associated with the model (stripping schema in sqlite).
     */
    public function getTable(): string
    {
        $table = parent::getTable();
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            $parts = explode('.', $table);
            return end($parts);
        }
        return $table;
    }

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
        'bin_allocations',
    ];

    protected $casts = [
        'picklist_id'           => 'integer',
        'sales_order_id'        => 'integer',
        'sales_order_detail_id' => 'integer',
        'ordered_qty'           => 'decimal:4',
        'pick_qty'              => 'decimal:4',
        'unit_weight'           => 'decimal:4',
        'total_weight'          => 'decimal:4',
        'bin_allocations'       => 'array',
    ];

    /**
     * Get the parent picklist.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Get the related sales order (from public schema / default connection).
     */
    public function salesOrder(): BelongsTo
    {
        $instance = new SalesOrder();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'sales_order_id', 'id', 'salesOrder');
    }

    /**
     * Get the related sales order line detail.
     */
    public function salesOrderDetail(): BelongsTo
    {
        $instance = new SalesOrderDetail();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'sales_order_detail_id', 'id', 'salesOrderDetail');
    }

    /**
     * Get the related master item.
     */
    public function item(): BelongsTo
    {
        $instance = new Item();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'item_code', 'item_code', 'item');
    }

    /**
     * Get the related warehouse.
     */
    public function warehouse(): BelongsTo
    {
        $instance = new Warehouse();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'whs_code', 'whs_code', 'warehouse');
    }
}
