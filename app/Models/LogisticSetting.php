<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogisticSetting extends Model
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
    protected $table = 'ekspedisi.logistic_settings';

    /**
     * Get the connection name for the model (fallback to sqlite in tests).
     */
    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
    }

    /**
     * Get the table name without schema when running sqlite.
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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Get setting value by key with optional fallback.
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public static function get(string $key, $default = null): ?string
    {
        try {
            $setting = static::where('key', $key)->first();
            return ($setting && $setting->value !== null && $setting->value !== '')
                ? (string) $setting->value
                : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Set setting value by key.
     *
     * @param string $key
     * @param string|null $value
     * @param string|null $description
     * @return self
     */
    public static function set(string $key, ?string $value, ?string $description = null): self
    {
        $payload = ['value' => $value];
        if ($description !== null) {
            $payload['description'] = $description;
        }

        return static::updateOrCreate(['key' => $key], $payload);
    }

    /**
     * Get the virtual warehouse code for Inventory Transfer (IT) and Delivery Order (DO).
     * Resolves from database setting first, then env/config fallback, defaulting to 'VPGMN01'.
     *
     * @return string
     */
    public static function getVirtualWarehouseCode(): string
    {
        $val = static::get('virtual_warehouse_code') ?: static::get('it_to_whs_code');
        if (!empty($val)) {
            return trim((string) $val);
        }

        return (string) (config('services.sap.it_to_whs_code') ?: env('SAP_IT_TO_WHS_CODE', 'VPGMN01'));
    }
}
