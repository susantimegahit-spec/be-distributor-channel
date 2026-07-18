<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMonthlyOrderDetail extends Model
{
    use HasFactory;

    protected $table = 'customer_monthly_order_details';

    protected $guarded = [];

    /**
     * Get the parent order that owns the detail.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerMonthlyOrder::class, 'customer_monthly_order_id');
    }
}
