<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderIntegrationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'request_json',
        'response_json',
        'status',
        'error_message',
    ];

    /**
     * Get the sales order associated with the log.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
