<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBomItem extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_bom_items';

    protected $fillable = [
        'production_bom_id',
        'father',
        'child_num',
        'type',
        'code',
        'quantity',
        'warehouse',
        'issue_mthd',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'comments',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'child_num' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function warehouseModel(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse', 'whs_code');
    }
}
