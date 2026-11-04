<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionChangeProductNewLine extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_production';
    protected $table = 'production.production_change_product_new_lines';

    public function getTable()
    {
        $conn = config('database.connections.' . ($this->connection ?: config('database.default')));
        if (($conn['driver'] ?? '') === 'sqlite' || config('database.default') === 'sqlite') {
            return 'production_change_product_new_lines';
        }
        return parent::getTable();
    }

    protected $fillable = [
        'production_change_product_id',
        'line_num',
        'item_code',
        'quantity',
        'to_whs_code',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'value_allocation_percent',
    ];

    protected $casts = [
        'line_num'                 => 'integer',
        'quantity'                 => 'float',
        'value_allocation_percent' => 'float',
    ];

    /**
     * Get the header Change Product transaction.
     */
    public function changeProduct(): BelongsTo
    {
        return $this->belongsTo(ProductionChangeProduct::class, 'production_change_product_id');
    }
}
