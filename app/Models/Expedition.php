<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedition extends Model
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
    protected $table = 'ekspedisi.expeditions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'expedition_code',
        'expedition_name',
        'address',
        'city',
        'province',
        'postal_code',
        'pic_name',
        'pic_phone',
        'email',
        'npwp',
        'vehicle_type',
        'transport_mode',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the rates for this expedition.
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ExpeditionRate::class, 'expedition_id', 'id');
    }

    /**
     * Get user who created record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get user who updated record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
