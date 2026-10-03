<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SapDiscountHeader extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sap_discount_headers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'discount_code',
        'card_code',
        'card_name',
        'total_so',
        'user_id',
    ];

    /**
     * Get the details associated with the header.
     */
    public function details(): HasMany
    {
        return $this->hasMany(SapDiscountDetail::class, 'sap_discount_header_id');
    }

    /**
     * Get the user that created the discount.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
