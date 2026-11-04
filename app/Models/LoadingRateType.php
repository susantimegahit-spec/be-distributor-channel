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
        'start_time',
        'end_time',
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
     * Check if a given time (format H:i or H:i:s, default now) falls within this rate type's active window.
     * Handles intervals that cross midnight (e.g. 16:30:00 to 02:00:00).
     */
    public function isTimeInRange(?string $time = null): bool
    {
        if (empty($this->start_time) || empty($this->end_time)) {
            return false;
        }

        $checkTime = $time ? substr($time, 0, 8) : now()->format('H:i:s');
        $start = substr((string) $this->start_time, 0, 8);
        $end = substr((string) $this->end_time, 0, 8);

        // Normalize string comparison length
        if (strlen($checkTime) === 5) {
            $checkTime .= ':00';
        }
        if (strlen($start) === 5) {
            $start .= ':00';
        }
        if (strlen($end) === 5) {
            $end .= ':00';
        }

        if ($start <= $end) {
            // Standard daytime interval, e.g. 08:00:00 to 16:30:00
            return $checkTime >= $start && $checkTime <= $end;
        }

        // Interval crosses midnight, e.g. 16:30:00 to 02:00:00
        return $checkTime >= $start || $checkTime <= $end;
    }

    /**
     * Get the rates associated with this calculation type.
     */
    public function loadingRates(): HasMany
    {
        return $this->hasMany(LoadingRate::class, 'rate_type_id');
    }
}
