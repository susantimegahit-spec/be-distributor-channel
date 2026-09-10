<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionIssueItem extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_issue_items';

    protected $fillable = [
        'production_issue_id',
        'production_order_id',
        'production_order_item_id',
        'line_num',
        'base_type',
        'base_entry',
        'base_line',
        'item_code',
        'quantity',
        'warehouse',
        'uom_entry',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'comments',
    ];

    protected $casts = [
        'quantity'  => 'decimal:4',
        'line_num'  => 'integer',
        'base_type' => 'integer',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(ProductionIssue::class, 'production_issue_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productionOrderItem(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderItem::class, 'production_order_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_code', 'item_code');
    }
}
