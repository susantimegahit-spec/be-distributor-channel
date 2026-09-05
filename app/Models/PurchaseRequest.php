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
        'series',
        'req_type',
        'requester',
        'department',
        'cost_center',
        'requester_id',
        'requester_name',
        'doc_date',
        'doc_due_date',
        'required_date',
        'total_amount',
        'status',
        'remarks',
        'comments',
        'user_id',
        'addon_id',
        'sap_doc_entry',
        'sap_doc_num',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'doc_date' => 'date',
        'doc_due_date' => 'date',
        'required_date' => 'date',
        'sap_doc_entry' => 'integer',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseRequestDetail::class, 'purchase_request_id');
    }
}
