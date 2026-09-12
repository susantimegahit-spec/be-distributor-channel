<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';
    protected $table = 'ekspedisi.districts';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
    }

    public function getTable()
    {
        return config('database.default') === 'sqlite' ? 'districts' : 'ekspedisi.districts';
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
        'regency_id',
        'name',
    ];

    /**
     * Get the regency that owns the district.
     */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_id', 'id');
    }

    /**
     * Get the villages for the district.
     */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'district_id', 'id');
    }
}
