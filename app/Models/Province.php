<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';
    protected $table = 'ekspedisi.provinces';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
    }

    public function getTable()
    {
        return config('database.default') === 'sqlite' ? 'provinces' : 'ekspedisi.provinces';
    }

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * Get the regencies for the province.
     */
    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'province_id', 'id');
    }
}
