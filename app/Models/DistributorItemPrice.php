<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorItemPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_customer',
        'item_code',
        'price',
        'created_by',
        'updated_by',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = [
        'depo',
    ];

    /**
     * Get the distributor's depo.
     */
    public function getDepoAttribute(): ?string
    {
        return $this->attributes['depo'] ?? $this->distributor?->depo;
    }

    /**
     * Get the distributor associated with this price.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'code_customer', 'code_customer');
    }

    /**
     * Get the item associated with this price.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
