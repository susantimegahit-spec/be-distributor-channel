<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicklistWorker extends Model
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
    protected $table = 'ekspedisi.picklist_workers';

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

    protected $fillable = [
        'picklist_id',
        'item_code',
        'item_name',
        'worker_name',
        'qty',
        'uom',
        'rate_type',
        'rate',
        'lembur_fee',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'picklist_id' => 'integer',
        'qty'         => 'decimal:4',
        'rate'        => 'decimal:2',
        'lembur_fee'  => 'decimal:2',
        'created_by'  => 'integer',
    ];

    /**
     * Get the picklist this worker entry belongs to.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
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
}
