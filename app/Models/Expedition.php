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
     * Generate automatic expedition code (e.g., EXP0001, EXP0002).
     *
     * @return string
     */
    public static function generateCode(): string
    {
        $lastExpedition = static::query()
            ->where('expedition_code', 'LIKE', 'EXP%')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastExpedition || !$lastExpedition->expedition_code) {
            return 'EXP0001';
        }

        $lastNum = (int) preg_replace('/[^0-9]/', '', $lastExpedition->expedition_code);
        $nextNum = $lastNum + 1;

        return 'EXP' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get user who updated record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
