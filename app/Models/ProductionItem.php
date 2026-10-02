<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionItem extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql_production';
    protected $table = 'production.production_items';

    public function getTable()
    {
        $conn = config('database.connections.' . ($this->connection ?: config('database.default')));
        if (($conn['driver'] ?? '') === 'sqlite' || config('database.default') === 'sqlite') {
            return 'production_items';
        }
        return parent::getTable();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'item_code',
        'item_name',
        'i_uom_entry',
        'invntry_uom',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'i_uom_entry' => 'integer',
        'is_active' => 'boolean',
    ];
}
