<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PicklistOverload extends Model
{
    use HasFactory;

    public const STATUS_PENDING_ADJUSTMENT = 'PENDING_ADJUSTMENT';
    public const STATUS_UNLOADED           = 'UNLOADED';
    public const STATUS_RESOLVED           = 'RESOLVED';
    public const STATUS_CANCELLED          = 'CANCELLED';

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
    protected $table = 'ekspedisi.picklist_overloads';

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
        'picklist_no',
        'gross_weight',
        'tare_weight',
        'net_weight',
        'weight_limit',
        'overload_weight',
        'ticket_no',
        'operator_name',
        'rejection_reason',
        'status',
        'items_snapshot',
        'action_taken',
        'weighed_at',
        'resolved_at',
        'resolved_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'picklist_id'     => 'integer',
        'gross_weight'    => 'float',
        'tare_weight'     => 'float',
        'net_weight'      => 'float',
        'weight_limit'    => 'float',
        'overload_weight' => 'float',
        'items_snapshot'  => 'array',
        'weighed_at'      => 'datetime',
        'resolved_at'     => 'datetime',
        'resolved_by'     => 'integer',
        'created_by'      => 'integer',
        'updated_by'      => 'integer',
    ];

    /**
     * Get the associated picklist.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Creator user relation.
     */
    public function creator(): BelongsTo
    {
        $instance = new User();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'created_by', 'id', 'creator');
    }

    /**
     * Resolver user relation.
     */
    public function resolver(): BelongsTo
    {
        $instance = new User();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'resolved_by', 'id', 'resolver');
    }
}
