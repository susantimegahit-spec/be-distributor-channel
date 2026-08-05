<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestDetail extends Model
{
    use HasFactory;

    protected $table = 'purchase_request_details';

    protected $fillable = [
        'purchase_request_id',
        'master_budget_id',
        'item_code',
        'item_description',
        'quantity',
        'uom',
        'unit_price',
        'line_total',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function masterBudget()
    {
        return $this->belongsTo(MasterBudget::class, 'master_budget_id');
    }
}
