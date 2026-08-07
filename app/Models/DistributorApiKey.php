<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorApiKey extends Model
{
    use HasFactory;

    protected $table = 'distributor_api_keys';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'allowed_ips' => 'array',
    ];

    /**
     * Get the distributor associated with the API key.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    /**
     * Hash the raw API key for secure storage & lookup.
     */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
?>
