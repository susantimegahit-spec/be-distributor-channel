<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerShipto extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_shiptos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'card_code',
        'name',
        'alias',
        'address',
        'city',
        'street',
        'transport_mode',
    ];

    /**
     * Get the distributor associated with the shipto.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'card_code', 'code_customer');
    }
}
