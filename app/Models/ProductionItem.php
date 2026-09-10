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

    /**
     * The table associated with the model.
     */
    protected $table = 'production.production_items';

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
