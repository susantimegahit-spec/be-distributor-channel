<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterUnit extends Model
{
    use HasFactory;

    protected $table = 'master_units';

    protected $fillable = [
        'unit_code',
        'unit_name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Get the warehouses associated with this master unit.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'master_unit_id', 'unit_code');
    }
}
