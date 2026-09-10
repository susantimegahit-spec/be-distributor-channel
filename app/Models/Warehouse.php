<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'whs_code',
        'whs_name',
        'master_unit_id',
        'status',
    ];

    protected $casts = [
        'master_unit_id' => 'string',
        'status' => 'integer',
    ];

    protected $appends = [
        'unit_code',
        'unit_name',
    ];

    /**
     * Get the master unit associated with this warehouse.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(MasterUnit::class, 'master_unit_id', 'unit_code');
    }

    /**
     * Alias relationship for unit.
     */
    public function masterUnit(): BelongsTo
    {
        return $this->belongsTo(MasterUnit::class, 'master_unit_id', 'unit_code');
    }

    /**
     * Get unit_code attribute helper.
     */
    public function getUnitCodeAttribute(): ?string
    {
        return $this->unit?->unit_code;
    }

    /**
     * Get unit_name attribute helper.
     */
    public function getUnitNameAttribute(): ?string
    {
        return $this->unit?->unit_name;
    }
}
