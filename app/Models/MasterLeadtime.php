<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterLeadtime extends Model
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
    protected $table = 'ekspedisi.master_leadtimes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_origin_id',
        'origin_warehouse_code',
        'origin_warehouse_name',
        'origin_city',
        'destination_id',
        'destination_regency_id',
        'destination_code',
        'destination_name',
        'destination_city',
        'destination_province',
        'avg_lead_time_days',
        'min_lead_time_days',
        'max_lead_time_days',
        'lead_time_unit',
        'transport_mode',
        'distance_km',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'avg_lead_time_days' => 'decimal:2',
        'min_lead_time_days' => 'decimal:2',
        'max_lead_time_days' => 'decimal:2',
        'distance_km' => 'decimal:2',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
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

    /**
     * Get the origin warehouse associated with this lead time.
     */
    public function warehouseOrigin(): BelongsTo
    {
        return $this->belongsTo(WarehouseOrigin::class, 'warehouse_origin_id');
    }

    /**
     * Get the destination regency associated with this lead time.
     */
    public function destinationRegency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'destination_regency_id');
    }

    /**
     * Get the user who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
