<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionResource extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql_production';

    /**
     * The table associated with the model.
     */
    protected $table = 'production.production_resources';

    public function getTable()
    {
        $conn = config('database.connections.' . ($this->connection ?: config('database.default')));
        if (($conn['driver'] ?? '') === 'sqlite' || config('database.default') === 'sqlite') {
            return 'production_resources';
        }
        return parent::getTable();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'res_code',
        'res_name',
        'unit_of_msr',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
