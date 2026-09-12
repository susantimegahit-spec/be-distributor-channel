<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regency extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';
    protected $table = 'ekspedisi.regencies';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_ekspedisi';
    }

    public function getTable()
    {
        return config('database.default') === 'sqlite' ? 'regencies' : 'ekspedisi.regencies';
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
        'province_id',
        'name',
    ];

    /**
     * Get the province that owns the regency.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id', 'id');
    }

    /**
     * Get the districts for the regency.
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'regency_id', 'id');
    }
}
