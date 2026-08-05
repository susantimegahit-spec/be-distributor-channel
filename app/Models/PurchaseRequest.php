<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_number',
        'department',
        'cost_center',
        'requester_id',
        'requester_name',
        'doc_date',
        'required_date',
        'total_amount',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'doc_date' => 'date',
        'required_date' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseRequestDetail::class, 'purchase_request_id');
    }
}
