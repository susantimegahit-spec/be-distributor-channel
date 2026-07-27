<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionRate extends Model
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
    protected $table = 'ekspedisi.expedition_rates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'expedition_id',
        'warehouse_id',
        'destination_id',
        'transport_mode',
        'service_type',
        'min_tonnage',
        'max_tonnage',
        'price',
        'eta_days',
        'min_shipment_qty',
        'max_shipment_qty',
        'valid_from',
        'valid_until',
        'status',
        'remarks',
        'upload_batch_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get expedition associated with this rate.
     */
    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_id');
    }

    /**
     * Get warehouse associated with this rate.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get destination associated with this rate.
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(CustomerShipto::class, 'destination_id');
    }

    /**
     * Get user who created record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get user who updated record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
