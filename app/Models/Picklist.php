<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Picklist extends Model
{
    use HasFactory;

    public const SHIPPING_TYPE_INTERNAL = 'internal';
    public const SHIPPING_TYPE_EXTERNAL = 'external';
    public const SHIPPING_TYPE_PICKUP   = 'pickup';

    public const STATUS_OPEN      = 'OPEN';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'picklists';

    protected $fillable = [
        'picklist_no',
        'shipping_type',
        'status',
        'posting_date',
        'due_date',
        'delivery_order_no',
        'total_weight_limit',
        'total_weight',
        'comments',
        'license_plate',
        'driver_name',
        'checker_name',
        'expedition_id',
        'expedition_name',
        'expedition_rate_id',
        'service_type',
        'estimated_cost',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'posting_date'       => 'date',
        'due_date'           => 'date',
        'total_weight_limit' => 'decimal:4',
        'total_weight'       => 'decimal:4',
        'estimated_cost'     => 'decimal:2',
        'created_by'         => 'integer',
        'updated_by'         => 'integer',
        'expedition_id'      => 'integer',
        'expedition_rate_id' => 'integer',
    ];

    /**
     * Get all items in this picklist.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PicklistItem::class, 'picklist_id');
    }

    /**
     * Get the user who created this picklist.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this picklist.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope query to active/open picklists.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope query by shipping type.
     */
    public function scopeByShippingType($query, string $type)
    {
        return $query->where('shipping_type', strtolower(trim($type)));
    }
}
