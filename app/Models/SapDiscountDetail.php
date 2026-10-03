<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SapDiscountDetail extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sap_discount_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sap_discount_header_id',
        'type_discount',
        'percentage',
        'total_discount',
        'remarks',
    ];

    /**
     * Get the casts array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'float',
            'total_discount' => 'float',
        ];
    }

    /**
     * Get the header associated with the detail.
     */
    public function header(): BelongsTo
    {
        return $this->belongsTo(SapDiscountHeader::class, 'sap_discount_header_id');
    }
}
