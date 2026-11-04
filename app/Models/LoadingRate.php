<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadingRate extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

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
    protected $table = 'ekspedisi.loading_rates';

    protected $fillable = [
        'item_code',
        'item_name',
        'rate_type_id',
        'rate_type',
        'uom',
        'rate',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate_type_id' => 'integer',
        'rate'         => 'decimal:2',
        'created_by'   => 'integer',
        'updated_by'   => 'integer',
    ];

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

    /**
     * Get the calculation / rate type.
     */
    public function calculationType(): BelongsTo
    {
        return $this->belongsTo(LoadingRateType::class, 'rate_type_id');
    }

    /**
     * Get the item from public master items table.
     */
    public function item(): BelongsTo
    {
        $instance = new Item();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo(
            $instance->newQuery(),
            $this,
            'item_code',
            'item_code',
            'item'
        );
    }

    /**
     * Get user who created record.
     */
    public function creator(): BelongsTo
    {
        $instance = new User();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo(
            $instance->newQuery(),
            $this,
            'created_by',
            'id',
            'creator'
        );
    }

    /**
     * Get user who updated record.
     */
    public function updater(): BelongsTo
    {
        $instance = new User();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo(
            $instance->newQuery(),
            $this,
            'updated_by',
            'id',
            'updater'
        );
    }
}
