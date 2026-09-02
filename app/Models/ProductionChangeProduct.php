<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionChangeProduct extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_change_products';

    public function getTable()
    {
        $conn = config('database.connections.' . ($this->connection ?: config('database.default')));
        if (($conn['driver'] ?? '') === 'sqlite' || config('database.default') === 'sqlite') {
            return 'production_change_products';
        }
        return parent::getTable();
    }

    protected $fillable = [
        'cp_no',
        'doc_date',
        'doc_due_date',
        'comments',
        'shift',
        'unit',
        'addon_id',
        'user_id',
        'gi_entry',
        'gr_entry',
        'status',
        'sap_status',
        'sap_message',
        'sap_error',
        'integrated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_date'      => 'datetime',
        'doc_due_date'  => 'datetime',
        'gi_entry'      => 'integer',
        'gr_entry'      => 'integer',
        'integrated_at' => 'datetime',
    ];

    /**
     * Get the detail items for the change product transaction.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductionChangeProductItem::class, 'production_change_product_id')->orderBy('line_num');
    }

    /**
     * Get the creator user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater user.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
