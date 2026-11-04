<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionIssue extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_issues';

    protected $fillable = [
        'doc_entry',
        'doc_num',
        'issue_no',
        'production_order_id',
        'doc_date',
        'doc_due_date',
        'u_shift',
        'u_unit',
        'bom_id',
        'comments',
        'status',
        'sap_status',
        'sap_error',
        'integrated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_date'      => 'date',
        'doc_due_date'  => 'date',
        'integrated_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionIssueItem::class, 'production_issue_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductionIssueItem::class, 'production_issue_id');
    }
}
