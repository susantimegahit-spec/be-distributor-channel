<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBom extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_boms';

    protected $fillable = [
        'code',
        'qty',
        'to_whs',
        'type',
        'alternate',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'u_shift',
        'u_unit',
        'comments',
        'is_active',
        'sap_doc_entry',
        'sap_doc_num',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'alternate' => 'integer',
        'is_active' => 'boolean',
        'sap_doc_entry' => 'integer',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(ProductionBomItem::class, 'production_bom_id');
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'code', 'item_code');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_whs', 'whs_code');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
