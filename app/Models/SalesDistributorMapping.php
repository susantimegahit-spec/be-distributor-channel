<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDistributorMapping extends Model
{
    use HasFactory;

    protected $table = 'sales_distributor_mappings';

    protected $fillable = [
        'code_customer',
        'slp_code',
        'status',
    ];

    protected $casts = [
        'slp_code' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = [
        'depo',
    ];

    /**
     * Get the depo attribute from distributor relation.
     */
    public function getDepoAttribute(): ?string
    {
        return $this->distributor?->depo;
    }

    /**
     * Get the distributor associated with the mapping.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'code_customer', 'code_customer');
    }

    /**
     * Get the sales employee associated with the mapping.
     */
    public function salesEmployee(): BelongsTo
    {
        return $this->belongsTo(SalesEmployee::class, 'slp_code', 'slp_code');
    }
}
