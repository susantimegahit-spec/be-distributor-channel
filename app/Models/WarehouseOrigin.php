<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseOrigin extends Model
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
    protected $table = 'ekspedisi.warehouse_origins';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'whs_name_origin',
        'whs_code',
        'whs_name',
        'street',
        'status',
        'created_by',
        'updated_by',
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
     * Get the master warehouse associated with the origin.
     */
    public function warehouse(): BelongsTo
    {
        $instance = new Warehouse();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo(
            $instance->newQuery(), $this, 'whs_code', 'whs_code', 'warehouse'
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
            $instance->newQuery(), $this, 'created_by', 'id', 'creator'
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
            $instance->newQuery(), $this, 'updated_by', 'id', 'updater'
        );
    }
}
