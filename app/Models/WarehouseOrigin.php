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
        return $this->belongsTo(Warehouse::class, 'whs_code', 'whs_code');
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
