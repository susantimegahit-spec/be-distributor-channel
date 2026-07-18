<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMonthlyOrder extends Model
{
    use HasFactory;

    protected $table = 'customer_monthly_orders';

    protected $guarded = [];

    /**
     * Get the details for the order.
     */
    public function details(): HasMany
    {
        return $this->hasMany(CustomerMonthlyOrderDetail::class, 'customer_monthly_order_id');
    }
}
