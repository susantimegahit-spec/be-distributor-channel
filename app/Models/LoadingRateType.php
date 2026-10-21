<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadingRateType extends Model
{
    use HasFactory;

    public const TYPE_REGULAR  = 'REGULAR';
    public const TYPE_OVERTIME = 'OVERTIME';

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
    protected $table = 'ekspedisi.loading_rate_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
     * Get the rates associated with this calculation type.
     */
    public function loadingRates(): HasMany
    {
        return $this->hasMany(LoadingRate::class, 'rate_type_id');
    }
}
