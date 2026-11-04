<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'checked_qty',
        'is_checked',
        'checked_by_checker',
        'checked_at',
        'start_time',
        'end_time',
        'production_date',
        'unit_weight',
        'total_weight',
        'bin_allocations',
        'delivery_order_no',
    ];

    protected $casts = [
        'picklist_id'           => 'integer',
        'sales_order_id'        => 'integer',
        'sales_order_detail_id' => 'integer',
        'ordered_qty'           => 'decimal:4',
        'pick_qty'              => 'decimal:4',
        'checked_qty'           => 'decimal:4',
        'is_checked'            => 'boolean',
        'checked_at'            => 'datetime',
        'start_time'            => 'datetime',
        'end_time'              => 'datetime',
        'production_date'       => 'date',
        'unit_weight'           => 'decimal:4',
        'total_weight'          => 'decimal:4',
        'bin_allocations'       => 'array',
    ];

    protected $appends = [
        'so_number',
        'order_number',
        'order_no',
        'sap_doc_num',
        'depo',
        'base_entry',
        'base_line',
        'labels',
    ];

    /**
     * Get BaseEntry from sales order.
     */
    public function getBaseEntryAttribute(): ?int
    {
        return $this->salesOrder?->docentry !== null
            ? (int) $this->salesOrder->docentry
            : ($this->salesOrder?->sap_doc_entry !== null ? (int) $this->salesOrder->sap_doc_entry : null);
    }

    /**
     * Get BaseLine from sales order detail line.
     */
    public function getBaseLineAttribute(): ?int
    {
        if ($this->relationLoaded('salesOrderDetail') && $this->salesOrderDetail) {
            return $this->salesOrderDetail->baseline !== null
                ? (int) $this->salesOrderDetail->baseline
                : ($this->salesOrderDetail->line_num !== null ? (int) $this->salesOrderDetail->line_num : 0);
        }
        return null;
    }

    /**
     * Get SO number formatted from sales order's sap_doc_num with fallback to order_no.
     */
    public function getSoNumberAttribute(): ?string
    {
        return $this->salesOrder?->sap_doc_num ?: $this->salesOrder?->order_no;
    }

    /**
     * Get order number formatted from sales order's sap_doc_num with fallback to order_no.
     */
    public function getOrderNumberAttribute(): ?string
    {
        return $this->salesOrder?->sap_doc_num ?: $this->salesOrder?->order_no;
    }

    /**
     * Get order number formatted from sales order's sap_doc_num with fallback to order_no.
     */
    public function getOrderNoAttribute(): ?string
    {
        return $this->salesOrder?->sap_doc_num ?: $this->salesOrder?->order_no;
    }

    /**
     * Get SAP DocNum from sales order.
     */
    public function getSapDocNumAttribute(): ?string
    {
        return $this->salesOrder?->sap_doc_num;
    }

    /**
     * Get Depo from sales order.
     */
    public function getDepoAttribute(): ?string
    {
        return $this->salesOrder?->depo;
    }

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

    /**
     * Get all label photos attached to this picklist item.
     */
    public function labelPhotos(): HasMany
    {
        return $this->hasMany(PicklistItemLabelPhoto::class, 'picklist_item_id');
    }

    /**
     * Get labels grouped by production_date with associated label photos.
     *
     * @return array
     */
    public function getLabelsAttribute(): array
    {
        $photos = $this->relationLoaded('labelPhotos')
            ? $this->labelPhotos
            : $this->labelPhotos()->get();

        if ($photos->isEmpty()) {
            if (!empty($this->production_date)) {
                $pDate = $this->production_date instanceof \DateTimeInterface
                    ? $this->production_date->format('Y-m-d')
                    : (string) $this->production_date;

                return [
                    [
                        'production_date' => $pDate,
                        'label_photos'    => [],
                    ],
                ];
            }
            return [];
        }

        $grouped = [];
        foreach ($photos as $photo) {
            $pDate = null;
            if (!empty($photo->production_date)) {
                $pDate = $photo->production_date instanceof \DateTimeInterface
                    ? $photo->production_date->format('Y-m-d')
                    : (string) $photo->production_date;
            } elseif (!empty($this->production_date)) {
                $pDate = $this->production_date instanceof \DateTimeInterface
                    ? $this->production_date->format('Y-m-d')
                    : (string) $this->production_date;
            }

            $key = $pDate ?: '_default';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'production_date' => $pDate,
                    'label_photos'    => [],
                ];
            }
            $grouped[$key]['label_photos'][] = $photo;
        }

        return array_values($grouped);
    }
}
